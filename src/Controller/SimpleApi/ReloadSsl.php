<?php

namespace App\Controller\SimpleApi;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ServiceRestart;

class ReloadSsl extends AbstractController
{
    #[Route('/simple-api/reload-ssl', name: 'simple_api_reload_ssl', methods: ['GET'])]
    public function reloadSsl(Request $request, MessageBusInterface $bus): Response
    {
        $apiKey = $request->headers->get('Simpleapikey');
        if ($apiKey != $_ENV['SIMPLEAPI_KEY']) {
            return new JsonResponse(['result' => 'ERROR'], Response::HTTP_UNAUTHORIZED);
        }

        $bus->dispatch(new ServiceRestart('apache2'));

        return new JsonResponse(['result' => 'OK'], Response::HTTP_OK);
    }
}
