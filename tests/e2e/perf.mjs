import { chromium } from '@playwright/test';
const [ statusCol, wordCol ] = process.argv.slice( 2 );
const b = await chromium.launch();
const ctx = await b.newContext( { acceptDownloads: true } );
const p = await ctx.newPage();
await p.goto( 'http://localhost:8930/wp-login.php' );
await p.fill( '#user_login', 'admin' );
await p.fill( '#user_pass', 'password' );
await Promise.all( [ p.waitForNavigation(), p.click( '#wp-submit' ) ] );
const f = ( rules ) => '&corinthian_filter=' + encodeURIComponent( JSON.stringify( { rules } ) );
const cases = [
	[ 'default list', '' ],
	[ 'sort Status (text meta)', `&orderby=corinthian-${ statusCol }&order=asc` ],
	[ 'sort Word Count', `&orderby=corinthian-${ wordCol }&order=desc` ],
	[ 'filter Status = Done', f( [ { column: statusCol, op: 'eq', value: 'Done' } ] ) ],
	[ 'filter Done + sort Word Count', f( [ { column: statusCol, op: 'eq', value: 'Done' } ] ) + `&orderby=corinthian-${ wordCol }&order=desc` ],
	[ 'filter Word Count between 100-200', f( [ { column: wordCol, op: 'between', value: [ '100', '200' ] } ] ) ],
	[ 'last page, sorted', `&orderby=corinthian-${ statusCol }&order=asc&paged=4900` ],
];
for ( const [ name, q ] of cases ) {
	const times = [];
	let items = '';
	for ( let i = 0; i < 3; i++ ) {
		const t0 = Date.now();
		const res = await p.goto( `http://localhost:8930/wp-admin/edit.php?post_type=post${ q }` );
		times.push( Date.now() - t0 );
		items = await p.$eval( '.tablenav.top .displaying-num', ( el ) => el.textContent ).catch( () => '?' );
		if ( res.status() !== 200 ) items += ` HTTP ${ res.status() }`;
	}
	console.log( `${ name.padEnd( 34 ) } median ${ times.sort( ( a, b ) => a - b )[ 1 ] } ms  (${ items })` );
}
await p.goto( `http://localhost:8930/wp-admin/edit.php?post_type=post${ f( [ { column: statusCol, op: 'eq', value: 'Done' } ] ) }` );
const t0 = Date.now();
const [ dl ] = await Promise.all( [ p.waitForEvent( 'download', { timeout: 300000 } ), p.click( 'a.corinthian-export' ) ] );
const { statSync, readFileSync } = await import( 'node:fs' );
const path = await dl.path();
const lines = readFileSync( path, 'utf8' ).trim().split( '\n' ).length;
console.log( `export Done (filtered)            ${ Date.now() - t0 } ms, ${ lines - 1 } rows, ${ Math.round( statSync( path ).size / 1024 ) } KB` );
await b.close();
