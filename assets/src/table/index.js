import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { doAction } from '@wordpress/hooks';
import './table.css';

const config = window.corinthianTable;

function columnKey( th ) {
	return th.id || [ ...th.classList ].find( ( c ) => c.startsWith( 'column-' ) )?.slice( 7 ) || '';
}

// Requests run one after another so a reset can never land before a save.
let queue = Promise.resolve();
function enqueue( request ) {
	queue = queue.then( () => apiFetch( request ).catch( () => {} ) );
	return queue;
}

function save( widths ) {
	return enqueue( {
		path: config.path,
		method: 'POST',
		data: { screen: config.screen, set: config.set, widths },
	} );
}

function reset() {
	return enqueue( {
		path: `${ config.path }?screen=${ encodeURIComponent( config.screen ) }&set=${ config.set }`,
		method: 'DELETE',
	} );
}

function setWidth( table, key, px ) {
	table
		.querySelectorAll( `thead .column-${ CSS.escape( key ) }, tfoot .column-${ CSS.escape( key ) }` )
		.forEach( ( cell ) => {
			cell.style.width = `${ px }px`;
		} );
}

function headers( table ) {
	return [ ...table.querySelectorAll( 'thead th.manage-column:not(.check-column)' ) ].filter( ( th ) => columnKey( th ) );
}

/**
 * Pin every column at its rendered pixel width and size the table to their
 * sum, so dragging one edge moves only that column instead of the fixed
 * layout redistributing the space.
 */
function freeze( table ) {
	if ( table.dataset.corinthianFrozen ) {
		return;
	}
	headers( table ).forEach( ( th ) => setWidth( table, columnKey( th ), Math.round( th.getBoundingClientRect().width ) ) );
	table.dataset.corinthianFrozen = '1';
	fitTable( table );
}

function fitTable( table ) {
	const check = table.querySelector( 'thead .check-column' );
	let total = check ? Math.round( check.getBoundingClientRect().width ) : 0;
	headers( table ).forEach( ( th ) => {
		total += parseInt( th.style.width, 10 ) || Math.round( th.getBoundingClientRect().width );
	} );
	table.style.width = `${ total }px`;
	table.style.minWidth = '0';
}

function allWidths( table ) {
	const out = {};
	headers( table ).forEach( ( th ) => {
		out[ columnKey( th ) ] = parseInt( th.style.width, 10 ) || Math.round( th.getBoundingClientRect().width );
	} );
	return out;
}

function attach( table ) {
	table.querySelectorAll( 'thead th.manage-column:not(.check-column)' ).forEach( ( th ) => {
		const key = columnKey( th );
		if ( ! key || th.querySelector( '.corinthian-resize' ) ) {
			return;
		}
		const handle = document.createElement( 'span' );
		handle.className = 'corinthian-resize';
		handle.setAttribute( 'role', 'separator' );
		handle.setAttribute( 'aria-orientation', 'vertical' );
		handle.setAttribute( 'aria-label', __( 'Resize column', 'corinthian-admin-column-manager' ) );
		handle.tabIndex = 0;
		th.appendChild( handle );

		const commit = () => save( allWidths( table ) );

		handle.addEventListener( 'pointerdown', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			const startX = event.clientX;
			const startWidth = th.getBoundingClientRect().width;
			let moved = false;
			handle.setPointerCapture( event.pointerId );
			document.body.classList.add( 'corinthian-resizing' );

			const move = ( e ) => {
				if ( ! moved ) {
					if ( Math.abs( e.clientX - startX ) < 3 ) {
						return;
					}
					moved = true;
					freeze( table );
				}
				setWidth( table, key, Math.max( config.minWidth, Math.round( startWidth + e.clientX - startX ) ) );
				fitTable( table );
			};
			const up = () => {
				handle.removeEventListener( 'pointermove', move );
				handle.removeEventListener( 'pointerup', up );
				document.body.classList.remove( 'corinthian-resizing' );
				if ( moved ) {
					commit();
				}
			};
			handle.addEventListener( 'pointermove', move );
			handle.addEventListener( 'pointerup', up );
		} );

		// The sort link sits under the header; a click that ends a drag must not sort.
		handle.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
		} );

		handle.addEventListener( 'keydown', ( event ) => {
			if ( event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' ) {
				return;
			}
			event.preventDefault();
			freeze( table );
			const step = event.shiftKey ? 50 : 10;
			const current = th.getBoundingClientRect().width;
			const next = Math.max( config.minWidth, Math.round( current + ( event.key === 'ArrowRight' ? step : -step ) ) );
			setWidth( table, key, next );
			fitTable( table );
			commit();
		} );

		handle.addEventListener( 'dblclick', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			reset().then( () => window.location.reload() );
		} );
	} );
}

if ( config ) {
	const run = () => {
		document.querySelectorAll( 'table.wp-list-table' ).forEach( ( table ) => {
			if ( config.resize !== false ) {
				attach( table );
			}
		} );
		const table = document.querySelector( 'table.wp-list-table' );
		let toolbar = null;
		if ( table ) {
			// Filter bar and bulk edit sit together above the table.
			toolbar = document.createElement( 'div' );
			toolbar.className = 'corinthian-toolbar';
			const anchor = table.closest( '.corinthian-scroll' ) || table;
			anchor.parentNode.insertBefore( toolbar, anchor );
		}
		doAction( 'corinthian.table.ready', { table, toolbar, config } );
	};
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
}
