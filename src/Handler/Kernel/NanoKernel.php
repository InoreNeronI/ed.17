<?php

namespace App\Handler\Kernel;

use App\Handler;
use Symfony\Component\HttpKernel;

/**
 * Class Kernel\Nano.
 */
class NanoKernel implements HttpKernel\HttpKernelInterface
{
    use Handler\KernelTrait;
}
