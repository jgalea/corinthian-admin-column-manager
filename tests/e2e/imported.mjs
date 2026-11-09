import { chromium } from '@playwright/test';
const b = await chromium.launch();
const p = await b.newPage( { viewport: { width: 1440, height: 900 } } );
await p.goto( 'http://localhost:8930/wp-login.php' );
await p.fill( '#user_login', 'admin' );
await p.fill( '#user_pass', 'password' );
await Promise.all( [ p.waitForNavigation(), p.click( '#wp-submit' ) ] );
await p.goto( 'http://localhost:8930/wp-admin/edit.php' );
const info = await p.evaluate( () => ( {
	cols: [ ...document.querySelectorAll( 'table.wp-list-table thead th' ) ].map( ( t ) => `${ t.innerText.split( '\n' )[ 0 ].trim() || t.id }:${ Math.round( t.getBoundingClientRect().width ) }` ),
	firstRow: [ ...document.querySelectorAll( 'table.wp-list-table tbody tr:first-child td' ) ].map( ( t ) => t.innerText.split( '\n' )[ 0 ].slice( 0, 30 ) ),
	errors: document.body.innerText.match( /(Fatal error|Warning|Notice|Deprecated):/g ) || [],
	collapsed: [ ...document.querySelectorAll( 'table.wp-list-table thead th:not(.check-column)' ) ].filter( ( t ) => t.getBoundingClientRect().width < 60 ).map( ( t ) => t.id ),
} ) );
console.log( JSON.stringify( info, null, 1 ) );
await p.screenshot( { path: process.argv[ 2 ] } );
await b.close();
process.exit( info.errors.length || info.collapsed.length ? 1 : 0 );
