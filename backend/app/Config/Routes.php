<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->options('(:any)', static fn() => service('response'));

$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {
    // Public
    $routes->post('auth/login', 'AuthController::login');

    // Protected
    $routes->group('', ['filter' => 'jwtAuth'], function ($routes) {
        $routes->post('auth/logout', 'AuthController::logout');

        // Users (admin only — enforced in controller)
        $routes->get('users', 'UserController::index');
        $routes->post('users', 'UserController::create');
        $routes->put('users/(:num)', 'UserController::update/$1');
        $routes->delete('users/(:num)', 'UserController::delete/$1');

        // Vehicles (admin only — enforced in controller)
        $routes->get('vehicles', 'VehicleController::index');
        $routes->post('vehicles', 'VehicleController::create');
        $routes->put('vehicles/(:num)', 'VehicleController::update/$1');
        $routes->delete('vehicles/(:num)', 'VehicleController::delete/$1');

        // Drivers (admin only — enforced in controller)
        $routes->get('drivers', 'DriverController::index');
        $routes->post('drivers', 'DriverController::create');
        $routes->put('drivers/(:num)', 'DriverController::update/$1');
        $routes->delete('drivers/(:num)', 'DriverController::delete/$1');

        // Bookings
        $routes->get('bookings', 'BookingController::index');
        $routes->post('bookings', 'BookingController::create');
        $routes->get('bookings/(:num)', 'BookingController::show/$1');
        $routes->post('bookings/(:num)/approve', 'BookingController::approve/$1');
        $routes->post('bookings/(:num)/reject', 'BookingController::reject/$1');

        // Dashboard
        $routes->get('dashboard', 'DashboardController::index');

        // Reports (admin only — enforced in controller)
        $routes->get('reports', 'ReportController::index');
        $routes->get('reports/export', 'ReportController::export');

        // Activity Logs
        $routes->get('activity-logs', 'ActivityLogController::index');
    });
});