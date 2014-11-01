<?php
/**
 * (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf\page;

use \de\toxa\txf\widget as widget;
use \de\toxa\txf\datasource\connection as connection;
use \de\toxa\txf\model_editor as model_editor;
use \de\toxa\txf\model_editor_static as model_editor_static;
use \de\toxa\txf\model_editor_text as model_editor_text;
use \de\toxa\txf\model_editor_texteditor as model_editor_texteditor;
use \de\toxa\txf\http_exception as http_exception;
use \de\toxa\txf\markup as markup;
use \de\toxa\txf\txf as txf;


/**
 * Implements widget for viewing and editing single page.
 *
 * @example
 *
 *     <?php namespace de\toxa\txf;
 *
 *     $widget = page\widget_editor::create()->processInput();
 *
 *     view::title( $widget->getPage()->title );
 *     view::main( $widget->getCode() );
 *
 * @package de\toxa\txf\page
 */

class widget_editor implements widget {

	/**
	 * Contains connected data source storing pages to be edited/viewed.
	 *
	 * @var connection
	 */

	protected $dataSource = null;

	/**
	 * Refers to model editor used for editing current page.
	 *
	 * @var model_editor
	 */

	protected $editor;

	/**
	 * Contains name of editor.
	 *
	 * @var string
	 */

	protected $editorName = 'pageEditor';

	/**
	 * Contains unique name of page to edit/view.
	 *
	 * @var string
	 */

	private $pageName;

	/**
	 * Refers to page managed in current instance of widget.
	 *
	 * This is false initially to lazily fetch instance on first demand.
	 *
	 * @var model_page
	 */

	private $page = false;

	/**
	 * Indicates whether current user is authorized to edit selected page or not.
	 *
	 * @var bool
	 */

	protected $mayEdit = false;

	/**
	 * Indicates whether current user wants to edit selected page or not.
	 *
	 * @var bool
	 */

	protected $wantEdit = false;

	/**
	 * Provides URL of script used to explicitly view current page.
	 *
	 * URL might include %s to be replaced by name of currently managed page.
	 *
	 * @var string
	 */

	protected $viewerUrl = false;

	/**
	 * Provides URL of script used to explicitly edit current page.
	 *
	 * URL might include %s to be replaced by name of currently managed page.
	 *
	 * @var string
	 */

	protected $editorUrl = false;

	/**
	 * Provides class of model to use on managing page.
	 *
	 * @var \ReflectionClass
	 */

	protected $modelClass;



	public function __construct( $pageName ) {
		if ( !is_string( $pageName ) || trim( $pageName ) === '' ) {
			throw new \InvalidArgumentException( 'invalid page name' );
		}

		$this->pageName = trim( $pageName );

		$this->modelClass = new \ReflectionClass( '\de\toxa\txf\page\model_page' );
	}

	/**
	 * Creates new instance for editing/viewing selected page.
	 *
	 * @param string $pageName unique name of page
	 * @return widget_editor
	 */

	public static function create( $pageName ) {
		return new static( $pageName );
	}

	/**
	 * Selects if current user may edit selected page or not.
	 *
	 * @param bool $mayEdit true if user may edit selected page, false otherwise
	 * @return $this
	 */

	public function setMayEdit( $mayEdit ) {
		$this->mayEdit = !!$mayEdit;

		return $this;
	}

	/**
	 * Selects if current user wants to edit selected page or not.
	 *
	 * @param bool $wantEdit true if user wants to edit selected page, false if user wants to view page
	 * @return $this
	 */

	public function setWantEdit( $wantEdit ) {
		$this->wantEdit = !!$wantEdit;

		return $this;
	}


	/**
	 * Selects name of form to use instead of default "login".
	 *
	 * @param string $editorName name of editor to use
	 * @return $this
	 */

	public function setEditorName( $editorName ) {
		if ( !is_string( $editorName ) || trim( $editorName ) === '' ) {
			throw new \InvalidArgumentException( 'invalid editor name' );
		}

		$this->editorName = trim( $editorName );

		return $this;
	}

	/**
	 * Enables support for redirecting to viewer URL after having edited page.
	 *
	 * @param string $url URL of viewer to be redirected to
	 * @return $this
	 */

	public function setViewerUrl( $url ) {
		if ( !is_string( $url ) || trim( $url ) === '' ) {
			throw new \InvalidArgumentException( 'invalid viewer URL' );
		}

		$this->viewerUrl = trim( $url );

		return $this;
	}

	/**
	 * Enables implicit support for extending code of widget to provide link to
	 * script given here for editing current page (if user is permitted to do so).
	 *
	 * @param string $url URL of editor to be redirected to
	 * @return $this
	 */

	public function setEditorUrl( $url ) {
		if ( !is_string( $url ) || trim( $url ) === '' ) {
			throw new \InvalidArgumentException( 'invalid editor URL' );
		}

		$this->editorUrl = trim( $url );

		return $this;
	}

	/**
	 * Selects model class to use instead of default (model_page).
	 *
	 * @param \ReflectionClass $modelClass
	 * @return $this
	 */

	public function setModelClass( \ReflectionClass $modelClass ) {
		if ( !$modelClass->isSubclassOf( '\de\toxa\txf\model_page' ) ) {
			throw new \InvalidArgumentException( 'model class must be subclass of model_page' );
		}

		$this->modelClass = $modelClass;

		return $this;
	}

	/**
	 * Retrieves reference on current widget's form (creating it if required).
	 *
	 * @return model_editor
	 */

	protected function getEditor() {
		if ( !$this->editor ) {
			$this->editor = $this->createEditor( $this->editorName );
		}

		return $this->editor;
	}

	/**
	 * Retrieves (cached) reference on current page.
	 *
	 * @param bool $force true to force reloading page from data source
	 * @return model_page|null
	 */

	public function getPage( $force = false ) {
		if ( $this->page === false || $force ) {
			$this->page = $this->modelClass->getMethod( 'find' )->invoke( null, $this->dataSource, array( 'name' => $this->pageName ) );
		}

		return $this->page;
	}

	/**
	 * Creates model editor used for editing page.
	 *
	 * @param string $editorName name of editor
	 * @return model_editor created instance
	 */

	protected function createEditor( $editorName ) {
		$page = $this->getPage();

		if ( $page ) {
			$editor   = model_editor::createOnItem( $this->dataSource, $page, $editorName );
			$pageName = $page->name;
		}  else {
			$editor   = model_editor::createOnModel( $this->dataSource, $this->modelClass, $editorName );
			$pageName = $this->pageName;
		}

		$editor
			->addField( 'name', \de\toxa\txf\_L('Name'), model_editor_static::create()->setContent( $pageName ) )
			->fixProperty( 'name', $pageName )
			->addField( 'title', \de\toxa\txf\_L('Title'), model_editor_text::create()->maximum( 255 )->trim( true )->collapseWhitespace( true )->mandatory() )
			->addField( 'content', \de\toxa\txf\_L('Content'), model_editor_texteditor::create( 10, 60 )->mandatory()->setClass( 'rte' ) );

		return $editor;
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @throws http_exception on unauthorized access for page editor
	 * @return $this current instance
	 */

	public function processInput() {

		if ( $this->mayEdit && $this->wantEdit ) {
			$editor = $this->getEditor();

			if ( $editor->processInput() ) {
				// editor has been closed ...
				if ( $this->viewerUrl ) {
					txf::redirectTo( sprintf( $this->viewerUrl, $this->pageName ) );
				} else {
					$this->wantEdit = false;

					$this->getPage( true );
				}
			}
		} else if ( !$this->mayEdit && $this->wantEdit ) {
			throw new http_exception( 403 );
		}


		return $this;
	}

	protected function getLinkToEditor() {
		return markup::block( markup::link( sprintf( $this->editorUrl, $this->pageName ), \de\toxa\txf\_L('Edit this page') ), 'editorPanel' );
	}

	/**
	 * Retrieves code for embedding widget in current view.
	 *
	 * @throws http_exception on trying to access missing page
	 * @return string code embeddable in view
	 */

	public function getCode() {
		if ( $this->mayEdit && $this->wantEdit ) {
			return $this->getEditor()->render();
		} else {
			$page = $this->getPage();

			if ( !$page ) {
				if ( $this->mayEdit ) {
					return markup::block( markup::emphasize( \de\toxa\txf\_L('Selected page does not exist, yet!') ), 'missing-page-content' ) .
					       ( $this->editorUrl ? $this->getLinkToEditor() : '' );
				}

				throw new http_exception( 404 );
			}

			$content = markup::block( $page->content, 'page-content' );

			if ( $this->editorUrl && $this->mayEdit ) {
				$content .= $this->getLinkToEditor();
			}

			return $content;
		}
	}
}
