<?php
use Doctum\Doctum;
use Symfony\Component\Finder\Finder;
use Doctum\RemoteRepository\GitHubRemoteRepository;

$root = realpath(__DIR__ . '/../') . '/';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($root . 'src');

return new Doctum($iterator, [
    'title'                => json_decode(file_get_contents($root . 'composer.json'))->description,
    'build_dir'            => $root . 'build/apidocs/',
    'cache_dir'            => $root . 'tmp/',
    'remote_repository'    => new GitHubRemoteRepository('phpmyadmin/sql-parser', $root),
]);
