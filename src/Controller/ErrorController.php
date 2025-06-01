<?php

namespace App\Controller;

use App\Service\ServiceManager;
use http\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class ErrorController extends \Exception implements Throwable
{
    protected $debug; // this is passed as a parameter from services.yaml
    protected $code;  // 404, 500, etc.
    protected $data;
    private $twig;
    private $service_manager;

    public function __construct(ServiceManager $serviceManager, Environment $twig, bool $debug)
    {
        $this->service_manager = $serviceManager;
        $this->twig = $twig;
        $this->debug = $debug;
    }
    /*
     * @Route("/custom/exception", name="custom_exception")
     */
    public function show(Request $request, FlattenException $exception)
    {
        $template = 'bundles/TwigBundle/Exception/error.html.twig';

        return new Response($this->twig->render(
            $template,
            [
                'header' => $this->service_manager->naveMenuItem(10),
                'footer' => $this->service_manager->naveMenuItem(18),
                'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
                'code' => $exception-> getStatusCode()
            ]
        ));
    }
}
