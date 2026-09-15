<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $templateFile = base_path('views/' . $template . '.php');
        if (!is_file($templateFile)) {
            throw new RuntimeException("View não encontrada: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        require base_path('views/' . $layout . '.php');
    }
}
