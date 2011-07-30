<?php

namespace Liip\VieBundle\Controller;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response,
    Symfony\Component\Routing\Exception\ResourceNotFoundException;

use FOS\RestBundle\View\ViewInterface;

use Doctrine\Common\Persistence\ObjectManager;

use Liip\VieBundle\FromJsonLdInterface;
use Liip\VieBundle\ToJsonLdInterface;

abstract class Controller
{
    /**
     * @var FOS\RestBundle\View\ViewInterface
     */
    protected $view;

    /**
     * @var Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var Doctrine\Common\Persistence\ObjectManager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $map;

    public function __construct(Request $request, ViewInterface $view, ObjectManager $manager, array $map)
    {
        $this->request = $request;
        $this->view = $view;
        $this->manager = $manager;
        $this->map = $map;
    }

    /**
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @param array $data
     * @return \Doctrine\ODM\PHPCR\DocumentRepository
     */
    protected function getRepository($data)
    {
        if (empty($this->map[$data['a']])) {
            throw new ResourceNotFoundException($data['a'].' is not mapped to a class');
        }

        return $this->manager->getRepository($this->map[$data['a']]);
    }

    /**
     * Handle article PUT
     */
    public function putDocumentAction($id)
    {
        $data = $this->request->request->all();

        $repository = $this->getRepository($data);
        $model = $repository->find($id);
        if (empty($model)) {
            throw new ResourceNotFoundException($id.' not found');
        }

        if (!($model instanceof FromJsonLdInterface)) {
            throw new \LogicException('Document does not support FromJsonLdInterface.');
        }

        $model->fromJsonLD($data);
        $this->manager->flush();

        if (!($model instanceof ToJsonLdInterface)) {
            return new Response('', 204);
        }

        // return the updated version
        $this->view->setParameters($model->toJsonLd());
        $this->view->setFormat('json');
        return $this->view->handle();
    }
}
