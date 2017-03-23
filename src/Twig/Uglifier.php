<?php

namespace Twig;

use Assetic;
use Assetic\Filter;
use Tholu\Packer;

class Uglifier
{
    /** @var Filter\FilterInterface */
    private $filter;

    /** @var Assetic\Asset\UglifyAsset asset */
    private $asset;

    /** @var bool */
    private $enabled;

    /**
     * Uglifier constructor.
     *
     * @param Filter\FilterInterface $filter
     * @param $enabled
     */
    public function __construct(Filter\FilterInterface $filter, $enabled)
    {
        $this->filter = $filter;
        $this->asset = new Assetic\Asset\UglifyAsset([$this->filter]);
        $this->enabled = $enabled;
    }

    /**
     * @param $content
     *
     * @return string
     */
    public function uglify($content)
    {
        if ($this->enabled) {
            $this->asset->loadContent($content);
            /** @var Packer\Packer $packer */
            $packer = new Packer\Packer($this->asset->dump(), 'Normal', true, false, true);

            return $packer->pack();
        }

        return $content;
    }
}
