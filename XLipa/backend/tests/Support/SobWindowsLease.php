<?php

namespace Tests\Support;

use LogicException;

/** Native read/share lease on disposable fixture files; no write-compatible lease. */
final class SobWindowsLease
{
    private $process;

    private array $pipes = [];

    public readonly array $identities;

    public function __construct(string $root, array $files)
    {
        SobFixture::root($root);
        if (PHP_OS_FAMILY !== 'Windows' || $files === [] || count($files) !== count(array_unique($files))) {
            throw new LogicException('SOB Windows fixture lease required.');
        }
        foreach ($files as $file) {
            if (! is_string($file) || ! preg_match('~^(nested/)?(source\.sqlite|backup\.sob|restore\.sob|fixture\.txt|second\.txt|artifact\.txt|contract\.json|executor\.txt|policy\.json)$~D', $file)) {
                throw new LogicException('SOB lease file rejected before access.');
            }
        }
        $command = ['C:/Windows/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', __DIR__.'/sob-windows-lease.ps1'];
        $this->process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $this->pipes, null, null, ['bypass_shell' => true, 'create_no_window' => true]);
        if (! is_resource($this->process)) {
            throw new LogicException('SOB native lease unavailable.');
        }
        fwrite($this->pipes[0], json_encode(['root' => $root, 'files' => $files], JSON_THROW_ON_ERROR)."\n");
        fflush($this->pipes[0]);
        $row = $this->response();
        if (($row['ok'] ?? false) !== true || ! is_array($row['identities'] ?? null)) {
            $this->close();
            throw new LogicException('SOB native lease rejected: '.($row['error'] ?? 'unavailable'));
        }
        $this->identities = $row['identities'];
    }

    private function response(): array
    {
        // Windows anonymous pipes are blocking; consume one framed response, not EOF.
        $bytes = fgets($this->pipes[1], 32768);
        if (is_string($bytes) && str_ends_with($bytes, "\n")) {
            return json_decode(trim($bytes), true, 16, JSON_THROW_ON_ERROR);
        }
        stream_set_blocking($this->pipes[2], false);
        $diagnostic = stream_get_contents($this->pipes[2]);
        $this->close();
        throw new LogicException('SOB native lease response failed: '.substr($diagnostic, 0, 4000));
    }

    public function alive(): void
    {
        if (! is_resource($this->process) || ! proc_get_status($this->process)['running']) {
            throw new LogicException('SOB native lease lost.');
        }
        if (@fwrite($this->pipes[0], "ping\n") === false) {
            throw new LogicException('SOB native lease lost.');
        }
        fflush($this->pipes[0]);
        if (($this->response()['ok'] ?? false) !== true) {
            throw new LogicException('SOB native lease lost.');
        }
    }

    public function digest(): string
    {
        return hash('sha256', SobFixture::canonical($this->identities));
    }

    public function close(): void
    {
        if (is_resource($this->process)) {
            @fwrite($this->pipes[0], "close\n");
            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
