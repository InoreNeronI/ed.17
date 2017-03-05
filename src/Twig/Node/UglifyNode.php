<?php

namespace App\Twig\Node;

use Twig_Compiler;
use Twig_Node;

class UglifyNode extends Twig_Node
{
    /**
     * UglifyNode constructor.
     *
     * @param Twig_Node $body
     * @param int       $lineNumber
     * @param string    $tag
     */
    public function __construct(Twig_Node $body, $lineNumber, $tag = 'uglify')
    {
        parent::__construct(['body' => $body], [], $lineNumber, $tag);
    }

    /**
     * {@inheritdoc}
     */
    public function compile(Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("ob_start();\n")
            ->subcompile($this->getNode('body'))
            ->write("echo \$context['_uglifier']->uglify(trim(ob_get_clean()));\n");
    }
}
