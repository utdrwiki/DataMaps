<?php

namespace MediaWiki\Extension\DataMaps\Libraries;

use MediaWiki\Config\ConfigException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;
use RuntimeException;
use Wikimedia\FileBackend\FSFile\TempFSFile;

class GDAL {
	private static function isImagePaletted( string $filePath ): bool {
		if ( Shell::isDisabled() ) {
			return false;
		}
		$result = Shell::command( 'gdalinfo', $filePath )->execute();
		if ( $result->getExitCode() !== 0 ) {
			throw new GDALException( 'gdalinfo', $result );
		}
		return str_contains( $result->getStdout(), 'ColorInterp=Palette' );
	}

	private static function convertToRGBA( string $filePath ): TempFSFile {
		if ( Shell::isDisabled() ) {
			throw new ConfigException( 'Shell commands are disabled, cannot convert image to RGBA' );
		}
		$tmpFile = MediaWikiServices::getInstance()
			->getTempFSFileFactory()
			->newTempFSFile( 'datamaps', 'vrt' );
		if ( $tmpFile === null ) {
			throw new RuntimeException( 'Failed to create temporary file for GDAL conversion' );
		}
		$outputFilePath = $tmpFile->getPath();
		$result = Shell::command(
			'gdal_translate',
			'-q',
			'-of', 'vrt',
			'-expand', 'rgba',
			$filePath,
			$outputFilePath,
		)
			->allowPath( $outputFilePath )
			->execute();
		if ( $result->getExitCode() !== 0 ) {
			$tmpFile->purge();
			throw new GDALException( 'gdal_translate', $result );
		}
		return $tmpFile;
	}

	public static function tileImage( string $filePath, string $tilesPath, array $args = [] ): void {
		if ( Shell::isDisabled() ) {
			throw new ConfigException( 'Shell commands are disabled, cannot tile map image' );
		}
		/** @var TempFSFile|null */
		$rgbaFile = null;
		if ( self::isImagePaletted( $filePath ) ) {
			$rgbaFile = self::convertToRGBA( $filePath );
			$filePath = $rgbaFile->getPath();
		}
		$quality = 90;
		if (
			isset( $args['quality'] ) &&
			\is_int( $args['quality'] ) &&
			$args['quality'] > 0 &&
			$args['quality'] <= 100
		) {
			$quality = $args['quality'];
		}
		$processes = 1;
		if (
			isset( $args['processes'] ) &&
			\is_int( $args['processes'] ) &&
			$args['processes'] > 0
		) {
			$processes = $args['processes'];
		}
		$result = Shell::command(
			'gdal2tiles.py',
			'-q',
			'-r', 'near',
			'-w', 'leaflet',
			'-p', 'raster',
			'--tiledriver', 'WEBP',
			'--webp-quality', $quality,
			'--processes', $processes,
			'--xyz',
			$filePath,
			$tilesPath,
		)
			// Python's multiprocessing semaphores do not work with sandboxing
			// for some reason.
			->disableSandbox()
			->execute();
		if ( $rgbaFile !== null ) {
			$rgbaFile->purge();
		}
		if ( $result->getExitCode() !== 0 ) {
			throw new GDALException( 'gdal2tiles.py', $result );
		}
	}
}
