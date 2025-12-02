<?php

namespace dokuwiki\plugin\dw2pdf\src;

class Cache extends \dokuwiki\Cache\Cache
{
    protected AbstractCollector $collector;

    /** @inheritdoc */
    public function __construct(Config $config, AbstractCollector $collector)
    {
        $this->collector = $collector;

        $pages = $collector->getPages();
        sort($pages);
        $key = implode(':', [
            implode(',', $pages),
            $config->getCacheKey(),
            $collector->getTitle(),
        ]);

        parent::__construct($key, '.dw2.pdf');

        $this->addDependencies();
    }

    /**
     * When this was a cache for a specific revision, remove it on destruction
     */
    public function __destruct()
    {
        if (!$this->collector->getRev()) return;
        $this->removeCache();
    }

    /** @inheritdoc */
    public function useCache($depends = [])
    {
        // when a specific revision is requested, do not use the cache
        if ($this->collector->getRev()) {
            return false;
        }

        return parent::useCache($depends);
    }

    /**
     * Note: we do not set up any dependencies to the plugin source code itself. On normal installation,
     * the config files will be touched which will invalidate the cache. During development, the developer
     * should manually purge the cache when changing the plugin code.
     * @inheritdoc
     */
    protected function addDependencies()
    {
        parent::addDependencies();

        // images and included pages
        $dependencies = [];
        foreach ($this->collector->getPages() as $pageid) {
            $relations = p_get_metadata($pageid, 'relation');

            if (is_array($relations)) {
                if (array_key_exists('media', $relations) && is_array($relations['media'])) {
                    foreach ($relations['media'] as $mediaid => $exists) {
                        if ($exists) {
                            $dependencies[] = mediaFN($mediaid);
                        }
                    }
                }

                if (array_key_exists('haspart', $relations) && is_array($relations['haspart'])) {
                    foreach ($relations['haspart'] as $part_pageid => $exists) {
                        if ($exists) {
                            $dependencies[] = wikiFN($part_pageid);
                        }
                    }
                }
            }

            $dependencies[] = metaFN($pageid, '.meta');
        }

        // set up the dependencies
        $this->depends['files'] = array_merge(
            $dependencies,
            $this->collector->getFiles(),
            getConfigFiles('main')
        );
    }
}
