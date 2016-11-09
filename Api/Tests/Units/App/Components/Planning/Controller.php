<?php
namespace Api\Tests\Units\App\Components\Planning;

use \Api\App\Components\Planning\Controller as _Controller;

/**
 * Classe de test du contrôleur de planning
 *
 * @author Prytoegrian <prytoegrian@protonmail.com>
 * @author Wouldsmina
 *
 * @since 0.1
 */
final class Controller extends \Api\Tests\Units\App\Libraries\AController
{
    /**
     * @var \mock\Api\App\Components\Planning\Repository Mock du repository associé
     */
    private $repository;

    /**
     * @var \mock\Api\App\Components\Planning\Creneau\Model Mock du modèle associé
     */
    private $model;

    /**
     * Init des tests
     */
    public function beforeTestMethod($method)
    {
        parent::beforeTestMethod($method);
        $this->mockGenerator->orphanize('__construct');
        $this->mockGenerator->shuntParentClassCalls();
        $this->repository = new \mock\Api\App\Components\Planning\Repository();
        $this->mockGenerator->orphanize('__construct');
        $this->model = new \mock\Api\App\Components\Planning\Model();
        $this->model->getMockController()->getId = 42;
        $this->model->getMockController()->getName = 12;
        $this->model->getMockController()->getStatus = 12;
    }

    /*************************************************
     * GET
     *************************************************/

    /**
     * Teste la méthode get d'un détail trouvé
     */
    public function testGetOneFound()
    {
        $this->repository->getMockController()->getOne = $this->model;
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->get($this->request, $this->response, ['planningId' => 99]);
        $data = $this->getJsonDecoded($response->getBody());

        $this->integer($response->getStatusCode())->isIdenticalTo(200);
        $this->array($data)
            ->integer['code']->isIdenticalTo(200)
            ->string['status']->isIdenticalTo('success')
            ->string['message']->isIdenticalTo('')
            ->array['data']->isNotEmpty()
        ;
    }

    /**
    * Teste la méthode get d'un détail non trouvé
     */
    public function testGetOneNotFound()
    {
        $this->repository->getMockController()->getOne = function () {
            throw new \DomainException('');
        };
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->get($this->request, $this->response, ['planningId' => 99]);

        $this->assertError($response, 404);
    }

    /**
     * Teste le fallback de la méthode get d'un détail
     */
    public function testGetOneFallback()
    {
        $this->repository->getMockController()->getOne = function () {
            throw new \Exception('');
        };
        $controller = new _Controller($this->repository, $this->router);

        $this->exception(function () use ($controller) {
            $controller->get($this->request, $this->response, ['planningId' => 99]);
        })->isInstanceOf('\Exception');
    }

    /**
    * Teste la méthode get d'une liste trouvée
     */
    public function testGetListFound()
    {
        $this->request->getMockController()->getQueryParams = [];
        $this->repository->getMockController()->getList = [
            42 => $this->model,
        ];
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->get($this->request, $this->response, []);
        $data = $this->getJsonDecoded($response->getBody());

        $this->integer($response->getStatusCode())->isIdenticalTo(200);
        $this->array($data)
            ->integer['code']->isIdenticalTo(200)
            ->string['status']->isIdenticalTo('success')
            ->string['message']->isIdenticalTo('')
            //->array['data']->hasSize(1) // l'asserter atoum en sucre syntaxique est buggé, faire un ticket
        ;
        $this->array($data['data'][0])->hasKey('id');
    }

    /**
    * Teste la méthode get d'une liste non trouvée
     */
    public function testGetListNotFound()
    {
        $this->request->getMockController()->getQueryParams = [];
        $this->repository->getMockController()->getList = function () {
            throw new \UnexpectedValueException('');

        };
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->get($this->request, $this->response, []);

        $this->assertError($response, 404);

    }

    /**
     * Teste le fallback de la méthode get d'une liste
     */
    public function testGetListFallback()
    {
        $this->request->getMockController()->getQueryParams = [];
        $this->repository->getMockController()->getList = function () {
            throw new \Exception('');
        };
        $controller = new _Controller($this->repository, $this->router);

        $this->exception(function () use ($controller) {
            $controller->get($this->request, $this->response, []);
        })->isInstanceOf('\Exception');
    }

    /*************************************************
     * POST
     *************************************************/

    /**
     * Teste la méthode post d'un json mal formé
     */
    public function testPostJsonBadFormat()
    {
        // Le framework fait du traitement, un mauvais json est simplement null
        $this->request->getMockController()->getParsedBody = null;
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->post($this->request, $this->response);

        $this->assertError($response, 400);
    }

    /**
     * Teste la méthode post avec des arguments manquants
     */
    public function testPostMissingRequiredArgs()
    {
        $this->request->getMockController()->getParsedBody = [];
        $this->repository->getMockController()->postOne = function () {
            throw new \Api\App\Exceptions\MissingArgumentException('');
        };
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->post($this->request, $this->response);

        $this->assertError($response, 412);
    }

    /**
     * Teste la méthode post avec un argument incohérent
     */
    public function testPostBadDomain()
    {
        $this->request->getMockController()->getParsedBody = [];
        $this->repository->getMockController()->postOne = function () {
            throw new \DomainException('Status doit être un int');
        };
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->post($this->request, $this->response);

        $this->assertError($response, 412);
    }

    /**
     * Teste la méthode post Ok
     */
    public function testPostOk()
    {
        $this->request->getMockController()->getParsedBody = [];
        $this->router->getMockController()->pathFor = '';
        $this->repository->getMockController()->postOne = 42;
        $controller = new _Controller($this->repository, $this->router);

        $response = $controller->post($this->request, $this->response);
        $data = $this->getJsonDecoded($response->getBody());

        $this->integer($response->getStatusCode())->isIdenticalTo(200);
        $this->array($data)
            ->integer['code']->isIdenticalTo(200)
            ->string['status']->isIdenticalTo('success')
            ->string['message']->isIdenticalTo('')
            ->array['data']->isNotEmpty()
        ;
    }

    /**
     * Teste le fallback de la méthode post
     */
    public function testGetPostFallback()
    {
        $this->request->getMockController()->getParsedBody = [];
        $this->repository->getMockController()->postOne = function () {
            throw new \Exception('');
        };
        $controller = new _Controller($this->repository, $this->router);

        $this->exception(function () use ($controller) {
            $controller->post($this->request, $this->response);
        })->isInstanceOf('\Exception');
    }
}
