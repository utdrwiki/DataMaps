<?php
namespace MediaWiki\Extension\DataMaps\Hooks;

use MediaWiki\Extension\DataMaps\API\ApiQueryDataMapEndpoint;
use MediaWiki\Extension\DataMaps\ExtensionConfig;
use ObjectCache;
use WikiPage;

final class ArticleMapCacheManagingHooks implements
    \MediaWiki\Page\Hook\ArticlePurgeHook,
    \MediaWiki\Hook\LocalFilePurgeThumbnailsHook
{
    public function __construct(
        private readonly ExtensionConfig $config
    ) { }

    /**
     * Purge processed map cache for the latest revision on action=purge.
     *
     * @param WikiPage $wikiPage WikiPage to purge
     * @return bool|void True or no return value to continue or false to abort
     */
    public function onArticlePurge( $wikiPage ) {
        $title = $wikiPage->getTitle();
        if ( $title->getNamespace() !== $this->config->getNamespaceId() ) {
            return;
        }

        $revision = $wikiPage->getRevisionRecord();
        if ( $revision === null || $revision->getId() === null ) {
            return;
        }

        $cache = ObjectCache::getInstance( $this->config->getApiCacheType() );
        $cacheKey = ApiQueryDataMapEndpoint::makeKey( $title, $revision->getId() );
        $cache->delete( $cacheKey );
    }

    /**
     * Deleted tiled files for a newly uploaded file if they exist.
     *
     * @param $file The file whose thumbnails to purge
     */
    public function onLocalFilePurgeThumbnails( $file, $archiveName, $urls ): void {
        if ( !$this->config->isTilingEnabled() ) {
            return;
        }
        $tiledPath = "{$this->config->getTileDirectory()}/{$file->getRel()}";
        if ( file_exists( $tiledPath ) ) {
            wfRecursiveRemoveDir( $tiledPath );
        }
    }
}
