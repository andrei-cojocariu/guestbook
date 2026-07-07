<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Guestbook::index');
$routes->get('Guestbook', 'Guestbook::index');
$routes->post('Guestbook/create', 'Guestbook::create');
