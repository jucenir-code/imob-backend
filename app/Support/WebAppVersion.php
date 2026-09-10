<?php

namespace App\Support;

class WebAppVersion
{
    public static function current(): ?string
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            return null;
        }

        // Fingerprint the compiled assets and worker, even when only a Vue/CSS file changes.
        return hash('sha256', file_get_contents($manifest).file_get_contents(public_path('sw.js')));
    }
}
