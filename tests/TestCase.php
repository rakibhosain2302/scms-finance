<?php

namespace SCMS\Finance\Tests;

use Nit\Master\MasterServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SCMS\Finance\SCMSFinanceServiceProvider;
use SCMS\Finance\Tests\Fixtures\Member;
use SCMS\Finance\Tests\Fixtures\User;

/** The application with nit-master and scms-finance, without a database. */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MasterServiceProvider::class, SCMSFinanceServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('nit.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('scms-finance.models.member', Member::class);
        $app['config']->set('scms-finance.tables.members', 'test_members');

        if ($database = env('FINANCE_TEST_DB_DATABASE')) {
            $app['config']->set('database.default', 'mysql');
            $app['config']->set('database.connections.mysql', array_merge($app['config']->get('database.connections.mysql'), [
                'driver' => env('FINANCE_TEST_DB_DRIVER', 'mariadb') === 'mysql' ? 'mysql' : 'mariadb',
                'host' => env('FINANCE_TEST_DB_HOST', '127.0.0.1'),
                'port' => env('FINANCE_TEST_DB_PORT', '3306'),
                'database' => $database,
                'username' => env('FINANCE_TEST_DB_USERNAME', 'root'),
                'password' => env('FINANCE_TEST_DB_PASSWORD', ''),
            ]));
        }
    }
}
