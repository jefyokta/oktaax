<?php

namespace Oktaax\Views;

use Oktaax\Error\ViewNotFound;
use Oktaax\Interfaces\View;

class PhpView implements View
{

   public function __construct(private string $viewsDir) {}


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
         return '<b>Error !</b>: '. $th->getMessage();
      }
   }
}
