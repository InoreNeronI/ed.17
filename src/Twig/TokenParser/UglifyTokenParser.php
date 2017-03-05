<?php

namespace App\Twig\TokenParser;

use App\Twig;
use Twig_Token;
use Twig_TokenParser;

class UglifyTokenParser extends Twig_TokenParser
{
    /**
     * @var bool
     */
    private $enabled;

    /**
     * UglifyTokenParser constructor.
     *
     * @param bool $enabled
     */
    public function __construct($enabled = true)
    {
        $this->enabled = (bool) $enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function parse(Twig_Token $token)
    {
        $lineNumber = $token->getLine();
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(function (Twig_Token $token) {
            return $token->test('enduglify');
        }, true);
        $this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
        if ($this->enabled) {
            $node = new Twig\Node\UglifyNode($body, $lineNumber, $this->getTag());

            return $node;
        }

        return $body;
    }

    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        return 'uglify';
    }
}
