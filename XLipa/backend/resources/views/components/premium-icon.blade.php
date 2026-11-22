<svg class="premium-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name ?? '')
@case('money')<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v5c0 4 16 4 16 0V5M4 10v5c0 4 16 4 16 0v-5M4 15v4c0 4 16 4 16 0v-4"/>@break
@case('check')<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>@break
@case('pending')<path d="M6 3h12M6 21h12M7 3v4l10 10v4M17 3v4L7 17v4"/>@break
@case('wallet')<path d="M4 6h15v14H3V5l13-3v4M19 10h-6v6h6"/><circle cx="15.5" cy="13" r=".5"/>@break
@case('link')<path d="m10 14 4-4M8 16l-1 1a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0M16 8l1-1a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0" transform="translate(1 0) scale(.9)"/>@break
@case('bank')<path d="m3 8 9-5 9 5H3ZM5 10v8M10 10v8M15 10v8M20 10v8M3 21h18M3 18h18"/>@break
@case('code')<path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/>@break
@case('chart')<path d="M4 20V10h3v10M10 20V4h3v16M16 20v-8h3v8"/>@break
@case('people')<circle cx="9" cy="7" r="3"/><path d="M3 21v-5a6 6 0 0 1 12 0v5M16 4a3 3 0 0 1 0 6M17 14a5 5 0 0 1 4 5v2"/>@break
@case('card')<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 9h20M5 14h5"/>@break
@case('home')<path d="m2 11 10-9 10 9M5 9v12h5v-7h4v7h5V9"/>@break
@case('settings')<circle cx="12" cy="12" r="4"/><path d="m9 3 6 0 1 4 4 2v6l-4 2-1 4H9l-1-4-4-2V9l4-2 1-4Z"/>@break
@default<path d="M7 6h14M7 12h14M7 18h14M2 6h1M2 12h1M2 18h1"/>
@endswitch
</svg>
