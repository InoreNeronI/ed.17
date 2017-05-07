<?php

namespace Assetic\Filter;

use Assetic\Asset;
use Assetic\Filter;
use Cache;

class UglifyFilter implements Filter\FilterInterface
{
    /**
     * Array containing app configurations.
     *
     * @var array
     */
    protected $options = [];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function filterLoad(Asset\AssetInterface $asset)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function filterDump(Asset\AssetInterface $asset)
    {
        //$asset->setContent(Minifier::minify($asset->getContent(), $this->options));
        $asset->setContent(Cache\CachedMinifier::minify($asset->getContent(), $this->options));
    }
}
