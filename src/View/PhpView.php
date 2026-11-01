<?php

namespace Oktaax\View;

use Oktaax\Exception\ViewNotFound;
use Oktaax\Contracts\View;

/**
 * The default template engine for oktaax
 * Before version 3, the default template engine was Blade,
 * but we chose to replace it with traditional PHP to simplify the library.
 */
class PhpView implements View
{
    public function __construct(private string $viewsDir) {}

    /**
     * Render a PHP template from the configured directory; data keys become variables and the template owns escaping.
     *
     * @param array<string, mixed> $data
     * @throws \Oktaax\Exception\ViewNotFound When the template does not exist.
     */
    public function render(string $view, array $data): ?string
    {
        try {
            $viewFile = $this->viewsDir . '/' . $view . '.php';
            if (file_exists($viewFile)) {
                ob_start();
                extract($data);
                include $viewFile;
                $viewContent = ob_get_clean();
                return $viewContent;
            } else {
                throw new ViewNotFound("View file not found: $viewFile");
            }
        } catch (\Throwable $th) {
            throw $th;
            return '<b>Error !</b>: ' . $th->getMessage();
        }
    }
}
