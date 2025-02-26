<?php

/**
 *
 * Copyright (c) Vladimír Macháček
 *
 * For the full copyright and license information, please view the file license.md
 * that was distributed with this source code.
 *
 */

declare(strict_types = 1);

namespace WebLoader;


class FilesCollectionRender
{

	private const
		LINK_ELEMENT = 'link',
		SCRIPT_ELEMENT = 'script',
		STYLE_ELEMENT = 'style',

		LINK_PREFETCH = 'prefetch',
		LINK_PRELOAD = 'preload',
		LINK_PRELOAD_AS_CSS = 'style',
		LINK_PRELOAD_AS_JS = 'script',

		SCRIPT_TYPE_ATTRIBUTE = 'text/javascript',
		STYLE_TYPE_ATTRIBUTE = 'text/css',

		VERSION_MARK = '?v=';

	/**
	 * @var Compiler
	 */
	private $compiler;

	/**
	 * @var string
	 */
	private $basePath;

	/**
	 * @var string
	 */
	private $selectedCollectionName;

	/**
	 * @var string
	 */
	private $selectedPrefix;


	public function __construct(Compiler $compiler)
	{
		$this->compiler = $compiler;
		$this->basePath = $this->createBasePath($compiler->getDocumentRoot(), $compiler->getOutputDir());
	}


	public function css(?string $collectionName = NULL, array $attributes = [], bool $loadContent = FALSE): string
	{
		$collection = $this->getSelectedCollection(Engine::CSS, $collectionName);
		$collectionName = $collection->getName();
		$this->compiler->compileFilesCollectionByType(Engine::CSS, $collectionName);
		$attributes = array_merge($attributes, $collection->getOutputElementAttributes());
		$attributes['type'] = self::STYLE_TYPE_ATTRIBUTE;
		$element = self::STYLE_ELEMENT;
		$basePath = $this->getCollectionBasePath($collectionName, Engine::CSS);
		$filePathParameter = NULL;

		if ($loadContent || $collection->isContentLoadingEnabled()) {
			$filePathParameter = $this->getCollectionFilePath($basePath);

		} else {
			$element = self::LINK_ELEMENT;
			$attributes['rel'] = 'stylesheet';
			$basePath = $this->compiler->getHostUrl() . $basePath;
			$attributes['href'] = $this->addVersionToBasePath($basePath);
		}

		return $this->generateElement($element, $attributes, $filePathParameter);
	}


	/**
	 * @param array|string|NULL $collectionsNames
	 * @return string
	 */
	public function cssPrefetch($collectionsNames = NULL): string
	{
		return $this->generateMetaLinkElements($collectionsNames, Engine::CSS, self::LINK_PREFETCH);
	}


	/**
	 * @param array|string|NULL $collectionsNames
	 * @return string
	 */
	public function cssPreload($collectionsNames = NULL): string
	{
		return $this->generateMetaLinkElements(
			$collectionsNames, Engine::CSS, self::LINK_PRELOAD, self::LINK_PRELOAD_AS_CSS
		);
	}


	public function getCompiler(): Compiler
	{
		return $this->compiler;
	}


	public function js(?string $collectionName = NULL, array $attributes = [], bool $loadContent = FALSE): string
	{
		$collection = $this->getSelectedCollection(Engine::JS, $collectionName);
		$collectionName = $collection->getName();
		$this->compiler->compileFilesCollectionByType(Engine::JS, $collectionName);
		$attributes = array_merge($attributes, $collection->getOutputElementAttributes());
		$attributes['type'] = self::SCRIPT_TYPE_ATTRIBUTE;
		$basePath = $this->getCollectionBasePath($collectionName, Engine::JS);
		$filePathParameter = NULL;

		if ($loadContent || $collection->isContentLoadingEnabled()) {
			$filePathParameter = $this->getCollectionFilePath($basePath);

		} else {
			$basePath = $this->compiler->getHostUrl() . $basePath;
			$attributes['src'] = $this->addVersionToBasePath($basePath);
		}

		return $this->generateElement(self::SCRIPT_ELEMENT, $attributes, $filePathParameter);
	}


	/**
	 * @param array|string|NULL $collectionsNames
	 * @return string
	 */
	public function jsPrefetch($collectionsNames = NULL): string
	{
		return $this->generateMetaLinkElements($collectionsNames, Engine::JS, self::LINK_PREFETCH);
	}


	/**
	 * @param array|string|NULL $collectionsNames
	 * @return string
	 */
	public function jsPreload($collectionsNames = NULL): string
	{
		return $this->generateMetaLinkElements(
			$collectionsNames, Engine::JS, self::LINK_PRELOAD, self::LINK_PRELOAD_AS_JS
		);
	}


	public function selectCollection(?string $collectionName = NULL): FilesCollectionRender
	{
		$this->selectedCollectionName = $collectionName;
		return $this;
	}


	public function setPrefix(?string $prefix = NULL): FilesCollectionRender
	{
		$this->selectedPrefix = $prefix;
		return $this;
	}


	private function addVersionToBasePath(string $path): string
	{
		return $path . self::VERSION_MARK . $this->compiler->getVersion();
	}


	private function createBasePath(string $documentRoot, string $outputDir): string
	{
		$basePath = $outputDir;

		if ($documentRoot) {
			$basePath = str_replace($documentRoot, '', $outputDir);
		}

		return trim($basePath, '/');
	}


	private function generateElement(string $element, array $attributes, ?string $filePath = NULL): string
	{
		$tag = '<' . $element;

		foreach ($attributes as $attribute => $value) {
			$tag .= ' ' . $attribute;

			if ($value !== TRUE) {
				$tag .= '="' . $value . '"';
			}
		}

		$tag .= ">";

		if ($filePath) {
			$tag .= $this->getFileContent($filePath);
		}

		if ($element !== self::LINK_ELEMENT) {
			$tag .= '</' . $element . '>';
		}

		$this->selectedPrefix = NULL;

		return $tag;
	}


	/**
	 * @param array|string|NULL $collectionsNames
	 */
	private function generateMetaLinkElements(
		$collectionsNames = NULL,
		string $collectionsType,
		string $rel,
		?string $as = NULL
	): string {
		$tags = '';
		$attributes['rel'] = $rel;

		if ($as) {
			$attributes['as'] = $as;
		}

		if ( ! $collectionsNames) {
			$collectionsNames[] = $this->getSelectedCollection($collectionsType)->getName();

		} elseif (is_string($collectionsNames)) {
			$collectionsNames = [$this->getSelectedCollection($collectionsType, $collectionsNames)->getName()];

		} else {
			foreach($collectionsNames as $collectionName) {
				$this->compiler->getFilesCollection($collectionsType, $collectionName);
			}
		}

		foreach ($collectionsNames as $collectionName) {
			$basePath = $this->getCollectionBasePath($collectionName, $collectionsType);
			$basePath = $this->compiler->getHostUrl() . $basePath;
			$attributes['href'] = $this->addVersionToBasePath($basePath);
			$tags .= $this->generateElement(self::LINK_ELEMENT, $attributes);
		}

		return $tags;
	}


	private function getCollectionBasePath(string $collectionName, string $type): string
	{
		if ($this->selectedPrefix) {
			$collectionName = $this->selectedPrefix . '.' . $collectionName;
		}

		$basePath = '/' . $collectionName . '.' . $type;

		if ($this->basePath) {
			$basePath = '/' . $this->basePath . $basePath;
		}

		return $basePath;
	}


	private function getCollectionFilePath(string $path): string
	{
		if ($this->compiler->getDocumentRoot()) {
			$path = rtrim($this->compiler->getDocumentRoot(), '/') . $path;
		}

		return $path;
	}


	private function getFileContent(string $path): string
	{
		return "\n" . file_get_contents($path) . "\n";
	}


	private function getSelectedCollection(string $type, ?string $collectionName = NULL): FilesCollection
	{
		if ( ! $collectionName && ! $this->selectedCollectionName) {
			throw new Exception('Trying to call files collection render on NULL.');
		}

		$collectionName = $collectionName ?? $this->selectedCollectionName;

		return $this->compiler->getFilesCollection($type, $collectionName);
	}

}
