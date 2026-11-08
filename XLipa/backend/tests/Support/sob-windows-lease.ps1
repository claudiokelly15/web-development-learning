param()
$ErrorActionPreference = 'Stop'
if ($env:APP_ENV -ne 'testing' -or $env:XLIPA_SYNTHETIC_ONLY -ne '1') { throw 'SOB synthetic bootstrap required' }
$inputLine = [Console]::ReadLine()
$request = $inputLine | ConvertFrom-Json
$tempPrefix = [IO.Path]::GetTempPath().TrimEnd('\').Replace('\', '/') + '/'
$root = [string]$request.root
if (-not $root.StartsWith($tempPrefix, [StringComparison]::Ordinal) -or $root.Substring($tempPrefix.Length) -cnotmatch '^xlipa-sob-[a-f0-9]{20}$') { throw 'SOB native fixture root rejected' }
Add-Type -TypeDefinition @'
using System;
using System.IO;
using System.Text;
using System.Collections.Generic;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;
public sealed class SobNativeLease : IDisposable {
    [StructLayout(LayoutKind.Sequential)] public struct Info {
        public uint Attributes; public System.Runtime.InteropServices.ComTypes.FILETIME Creation;
        public System.Runtime.InteropServices.ComTypes.FILETIME Access; public System.Runtime.InteropServices.ComTypes.FILETIME Write;
        public uint Volume, SizeHigh, SizeLow, Links, IdHigh, IdLow;
    }
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)] static extern SafeFileHandle CreateFileW(string name, uint access, uint share, IntPtr security, uint disposition, uint flags, IntPtr template);
    [DllImport("kernel32.dll", SetLastError=true)] static extern bool GetFileInformationByHandle(SafeFileHandle handle, out Info info);
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)] static extern uint GetFinalPathNameByHandleW(SafeFileHandle handle, StringBuilder text, uint size, uint flags);
    readonly List<SafeFileHandle> handles = new List<SafeFileHandle>();
    readonly Dictionary<string, string> identities = new Dictionary<string, string>();
    public string Hold(string path, bool directory) {
        string full = Path.GetFullPath(path);
        var handle = CreateFileW(full, directory ? 0x80u : 0x80000000u, directory ? 3u : 1u, IntPtr.Zero, 3, 0x02200000, IntPtr.Zero);
        if (handle.IsInvalid) { handle.Dispose(); throw new IOException("SOB native open refused: " + Marshal.GetLastWin32Error()); }
        handles.Add(handle);
        Info info;
        if (!GetFileInformationByHandle(handle, out info) || (info.Attributes & 0x400) != 0 || ((info.Attributes & 0x10) != 0) != directory || (!directory && info.Links != 1)) throw new IOException("SOB native reparse/type/hardlink rejected");
        var buffer = new StringBuilder(32768);
        uint size = GetFinalPathNameByHandleW(handle, buffer, (uint)buffer.Capacity, 0);
        if (size == 0 || size >= buffer.Capacity) throw new IOException("SOB native path unavailable");
        string resolved = buffer.ToString();
        if (resolved.StartsWith(@"\\?\")) resolved = resolved.Substring(4);
        if (!String.Equals(resolved.TrimEnd('\\'), full.TrimEnd('\\'), StringComparison.Ordinal)) throw new IOException("SOB native canonical alias rejected");
        string identity = info.Volume.ToString("x8") + ":" + info.IdHigh.ToString("x8") + info.IdLow.ToString("x8");
        identities[full] = identity;
        return identity;
    }
    public void Parents(string path) {
        string current = Path.GetFullPath(path);
        var parents = new List<string>();
        while (current != null) { parents.Add(current); current = Path.GetDirectoryName(current); }
        parents.Reverse();
        foreach (string parent in parents) if (!identities.ContainsKey(parent)) Hold(parent, true);
    }
    public void Check() {
        foreach (var handle in handles) {
            Info info;
            if (!GetFileInformationByHandle(handle, out info) || (info.Attributes & 0x400) != 0 || ((info.Attributes & 0x10) == 0 && info.Links != 1)) throw new IOException("SOB native lease changed");
        }
    }
    public void Dispose() { for (int i=handles.Count-1; i>=0; i--) handles[i].Dispose(); handles.Clear(); }
}
'@
$lease = New-Object SobNativeLease
try {
    $lease.Parents($root)
    if ($request.mode -eq 'junction') {
        $target = [string]$request.target
        if (-not $target.StartsWith($tempPrefix, [StringComparison]::Ordinal) -or $target.Substring($tempPrefix.Length) -cnotmatch '^xlipa-sob-[a-f0-9]{20}$') { throw 'SOB junction target rejected' }
        $lease.Parents($target)
        New-Item -ItemType Junction -Path (Join-Path $root 'nested') -Target $target | Out-Null
        [Console]::WriteLine('{"ok":true}')
        exit 0
    }
    $identities = [ordered]@{}
    $identities['root'] = $lease.Hold($root, $true)
    foreach ($name in $request.files) {
        if ([string]$name -cnotmatch '^(nested/)?(source\.sqlite|backup\.sob|restore\.sob|fixture\.txt|second\.txt|artifact\.txt|contract\.json|executor\.txt|policy\.json)$') { throw 'SOB native filename rejected' }
        $path = Join-Path $root ([string]$name)
        $lease.Parents([IO.Path]::GetDirectoryName($path))
        $identities[[string]$name] = $lease.Hold($path, $false)
    }
    [Console]::WriteLine((@{ok=$true;identities=$identities} | ConvertTo-Json -Compress -Depth 8))
    while ($null -ne ($command = [Console]::ReadLine())) {
        if ($command -eq 'close') { break }
        if ($command -ne 'ping') { throw 'SOB native command rejected' }
        $lease.Check()
        [Console]::WriteLine('{"ok":true}')
    }
} catch {
    [Console]::WriteLine((@{ok=$false;error=$_.Exception.Message} | ConvertTo-Json -Compress))
    exit 2
} finally {
    $lease.Dispose()
}
