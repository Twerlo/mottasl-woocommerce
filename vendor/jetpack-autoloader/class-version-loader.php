<?php
/**
 * This file was automatically generated by automattic/jetpack-autoloader.
 *
 * @package automattic/jetpack-autoloader
 */

namespace Automattic\Jetpack\Autoloader\jp5fa35b2f983ccac6b957dc9e06081fe7\al2_12_0;

 // phpcs:ignore

/**
 * This class loads other classes based on given parameters.
 */
class Version_Loader {

	/**
	 * The Version_Selector object.
	 *
	 * @var Version_Selector
	 */
	private $version_selector;

	/**
	 * A map of available classes and their version and file path.
	 *
	 * @var array
	 */
	private $classmap;

	/**
	 * A map of PSR-4 namespaces and their version and directory path.
	 *
	 * @var array
	 */
	private $psr4_map;

	/**
	 * A map of all the files that we should load.
	 *
	 * @var array
	 */
	private $filemap;

	/**
	 * The constructor.
	 *
	 * @param Version_Selector $version_selector The Version_Selector object.
	 * @param array            $classmap The verioned classmap to load using.
	 * @param array            $psr4_map The versioned PSR-4 map to load using.
	 * @param array            $filemap The versioned filemap to load.
	 */
	public function __construct( $version_selector, $classmap, $psr4_map, $filemap ) {
		$this->version_selector = $version_selector;
		$this->classmap         = $classmap;
		$this->psr4_map         = $psr4_map;
		$this->filemap          = $filemap;
	}

	/**
	 * Finds the file path for the given class.
	 *
	 * @param string $class_name The class to find.
	 *
	 * @return string|null $file_path The path to the file if found, null if no class was found.
	 */
	public function find_class_file( $class_name ) {
		$data = $this->select_newest_file(
			isset( $this->classmap[ $class_name ] ) ? $this->classmap[ $class_name ] : null,
			$this->find_psr4_file( $class_name )
		);
		if ( ! isset( $data ) ) {
			return null;
		}

		return $data['path'];
	}

	/**
	 * Load all of the files in the filemap.
	 */
	public function load_filemap() {
		if ( empty( $this->filemap ) ) {
			return;
		}

		foreach ( $this->filemap as $file_identifier => $file_data ) {
			if ( empty( $GLOBALS['__composer_autoload_files'][ $file_identifier ] ) ) {
				require_once $file_data['path'];

				$GLOBALS['__composer_autoload_files'][ $file_identifier ] = true;
			}
		}
	}

	/**
	 * Compares different class sources and returns the newest.
	 *
	 * @param array|null $classmap_data The classmap class data.
	 * @param array|null $psr4_data The PSR-4 class data.
	 *
	 * @return array|null $data
	 */
	private function select_newest_file( $classmap_data, $psr4_data ) {
		if ( ! isset( $classmap_data ) ) {
			return $psr4_data;
		} elseif ( ! isset( $psr4_data ) ) {
			return $classmap_data;
		}

		if ( $this->version_selector->is_version_update_required( $classmap_data['version'], $psr4_data['version'] ) ) {
			return $psr4_data;
		}

		return $classmap_data;
	}

	/**
	 * Finds the file for a given class in a PSR-4 namespace.
	 *
	 * @param string $class_name The class to find.
	 *
	 * @return array|null $data The version and path path to the file if found, null otherwise.
	 */
	private function find_psr4_file( $class_name ) {
		if ( ! isset( $this->psr4_map ) ) {
			return null;
		}

		// Don't bother with classes that have no namespace.
		$class_index = strrpos( $class_name, '\\' );
		if ( ! $class_index ) {
			return null;
		}
		$class_for_path = str_replace( '\\', '/', $class_name );

		// Search for the namespace by iteratively cutting off the last segment until
		// we find a match. This allows us to check the most-specific namespaces
		// first as well as minimize the amount of time spent looking.
		for (
			$class_namespace = substr( $class_name, 0, $class_index );
			! empty( $class_namespace );
			$class_namespace = substr( $class_namespace, 0, strrpos( $class_namespace, '\\' ) )
		) {
			$namespace = $class_namespace . '\\';
			if ( ! isset( $this->psr4_map[ $namespace ] ) ) {
				continue;
			}
			$data = $this->psr4_map[ $namespace ];

			foreach ( $data['path'] as $path ) {
				$path .= '/' . substr( $class_for_path, strlen( $namespace ) ) . '.php';
				if ( file_exists( $path ) ) {
					return array(
						'version' => $data['version'],
						'path'    => $path,
					);
				}
			}
		}

		return null;
	}
}
