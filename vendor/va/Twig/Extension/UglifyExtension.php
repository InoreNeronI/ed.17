<?php

namespace Twig\Extension;

use Twig;
use Twig_Extension;
use Twig_Extension_GlobalsInterface;

class UglifyExtension extends Twig_Extension implements Twig_Extension_GlobalsInterface
{
    private $uglifier;

    /**
     * UglifyExtension constructor.
     *
     * @param Twig\Uglifier $uglifier
     */
    public function __construct(Twig\Uglifier $uglifier)
    {
        $this->uglifier = $uglifier;
    }

    /**
     * @return array
     */
    public function getGlobals()
    {
        return [
            '_uglifier' => $this->uglifier,
        ];
    }

    /**
     * @return array
     */
    public function getTokenParsers()
    {
        return [new Twig\TokenParser\UglifyTokenParser()];
    }
}
