<?php

namespace App\EventListener;


use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

#[AsEventListener(

)]
class ApiControllerListener
{
    /**
     * @param \Symfony\Component\Serializer\Serializer $serializer
     */
    public function __construct(
        private readonly SerializerInterface $serializer
    )
    {

    }

    public function __invoke(ControllerEvent $event): void {
        $request = $event->getRequest();
        $language = $request->getLocale();
        $pathTo = $request->get('_route');

        if ($pathTo === 'app_api_login' || $pathTo === 'app_offer') {
            return;
        }

        if ($request->getMethod() !== Request::METHOD_GET) {
            $params = $this->serializer->decode($request->getContent(), 'json');
            $paramsIn = is_array($params) ? $params : $request->request->all();
            $paramsIn['lang'] = $language;
            $request->request->replace($paramsIn);
        }
    }

}
