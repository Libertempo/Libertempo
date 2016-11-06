<?php
/*
 * Doit être importé après la création de $app.
 *
 * /!\ Les Middlewares sont executés en mode PILE : le premier de la liste est lancé en dernier
 */
use Psr\Http\Message\ServerRequestInterface as IRequest;
use Psr\Http\Message\ResponseInterface as IResponse;

/* Middleware 6 : construction du contrôleur pour le Dependencies Injection Container */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    $ressourcePath = str_replace('|', '\\', $request->getAttribute('nomRessources'));
    $controllerClass = '\Api\App\Components\\' . $ressourcePath . '\Controller';
    $daoClass = '\Api\App\Components\\' . $ressourcePath . '\Dao';
    $repoClass = '\Api\App\Components\\' . $ressourcePath . '\Repository';

    if (class_exists($controllerClass, true)) {
        $this[$controllerClass] = new $controllerClass(
            new $repoClass(
                new $daoClass($this['storageConnector'])
            )
        );

        return $next($request, $response);
    } else {
        return call_user_func(
            $this->notFoundHandler,
            $request,
            $response
        );
    }
});

/* Middleware 5 : découverte et mise en forme des noms de ressources */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    $path = trim(trim($request->getUri()->getPath()), '/');
    $paths = explode('/', $path);
    $ressources = [];
    foreach ($paths as $value) {
        if (!is_numeric($value)) {
            $ressources[] = \Api\App\Helpers\Formatter::getSingularTerm(
                \Api\App\Helpers\Formatter::getStudlyCapsFromSnake($value)
            );
        }
    }
    $request = $request->withAttribute('nomRessources', implode('|', $ressources));

    return $next($request, $response);
});

/* Middleware 4 : connexion DB */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    try {
        require_once CONFIG_PATH . 'dbconnect.php';
        $this['storageConnector'] = new \PDO(
            'mysql:host=localhost;dbname=' . $mysql_database,
            $mysql_user,
            $mysql_pass
        );

    return $next($request, $response);
    /* Fallback */
    } catch (\Exception $e) {
        return call_user_func(
            $this->errorHandler,
            $request,
            $response,
            $e
        );
    }
});

/* Middleware 3 : vérification des headers (peut-être 1 ?) */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    /* /!\ Headers non versionnés */
    $json = 'application/json';
    if (($request->hasHeader('Accept') && $request->getHeaderLine('Accept') === $json)
        && ($request->hasHeader('Content-Type') && $request->getHeaderLine('Content-Type') === $json)
    ) {
        return $next($request, $response);
    } else {
        return call_user_func(
            $this->badRequestHandler,
            $request,
            $response
        );
    }
});

/* Middleware 2 : sécurité via droits d'accès sur la ressource */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    /**
    * TODO
    *
    * qu'est ce que ça veut dire qu'une ressource est accessible, et où le mettre ? dépend du rôle ?
    */
    if (true) {
        return $next($request, $response);
    } else {
        return call_user_func(
            $this->forbiddenHandler,
            $request,
            $response
        );
    }
});

/* Middleware 1 : sécurité via authentification */
$app->add(function (IRequest $request, IResponse $response, callable $next) {
    /**
    * TODO
    */
    if ((new \Api\Middlewares\Authentication($request))->isTokenApiOk()) {
        return $next($request, $response);
    } else {
        return call_user_func(
            $this->unauthorizedHandler,
            $request,
            $response
        );
    }
});
