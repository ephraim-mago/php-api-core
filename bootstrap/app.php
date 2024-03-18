<?php

$app = new Framework\Core\Application(
    dirname(__DIR__)
);

$app->withKernels();

$app->withExceptions();

return $app;
