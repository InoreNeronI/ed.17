<?php

namespace App\Assetic\Filter;

use App\Cache;
use Assetic\Asset;
use Assetic\Filter;

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
        $asset->setContent(Cache\CachedMinifier::uglify($asset->getContent(), $this->options));
    }
}
