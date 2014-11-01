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

use \de\toxa\txf\model as model;

class model_page extends model {

	protected static $set = 'txf_page';
	protected static $label = 'title';

	public static function label( $count = 1 ) {
		return \de\toxa\txf\_L('page','pages',$count);
	}

	public static function define() {
		return array(
			'name'         => 'CHAR(32) NOT NULL UNIQUE',
		    'title'        => 'CHAR(255)',
		    'content'      => 'LONGTEXT',
		    'visible_from' => 'DATE',
		    'visible_til'  => 'DATE',
			'menuid'       => 'CHAR(16)',
		    'menusort'     => 'INTEGER',
		);
	}
}
