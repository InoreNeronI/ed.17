<?php

namespace App\Assetic\Asset;

use Assetic\Asset;
use Assetic\Filter;

class UglifyAsset extends Asset\BaseAsset
{
    private $theContent;

    /**
     * UglifierAsset constructor.
     *
     * @param array $filters
     */
    public function __construct($filters = [])
    {
        parent::__construct($filters);
    }

    /**
     * @param $content
     */
    public function loadContent($content)
    {
        $this->theContent = $content;
        $this->load();
    }

    /**
     * @param Filter\FilterInterface|null $additionalFilter
     */
    public function load(Filter\FilterInterface $additionalFilter = null)
    {
        $this->doLoad($this->theContent);
    }

    /**
     * @return int
     */
    public function getLastModified()
    {
        return 0;
    }
}
