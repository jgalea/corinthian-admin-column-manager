import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';

const base = 'http://localhost:8930';
// Local test site: set WP_CLI_CONTAINER to your wp-env cli container.
const CLI = process.env.WP_CLI_CONTAINER || 'wp-env-cli';
const wp = ( cmd ) => execSync( `docker exec ${ CLI } wp ${ cmd }`, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();
wp( 'eval-file wp-content/plugins/corinthian-admin-column-manager/tests/e2e/helpers/reverse-set.php post:page' );

const browser = await browser_login( 'admin', 'password' );
async function browser_login( user, pass ) {
	const b = await chromium.launch();
	const p = await b.newPage( { viewport: { width: 1440, height: 900 } } );
	await p.goto( `${ base }/wp-login.php` );
	await p.fill( '#user_login', user );
	await p.fill( '#user_pass', pass );
	await Promise.all( [ p.waitForNavigation(), p.click( '#wp-submit' ) ] );
	return { b, p };
}
const { b, p } = browser;
const url = `${ base }/wp-admin/edit.php?post_type=page`;
const width = ( page, id ) => page.$eval( `th#${ id }`, ( t ) => Math.round( t.getBoundingClientRect().width ) );
let fail = 0;
const check = ( ok, msg ) => { console.log( ok ? 'PASS' : 'FAIL', msg ); if ( ! ok ) fail++; };

await p.goto( url );
// Start clean: clear any widths a previous run saved.
await p.evaluate( ( c ) => wp.apiFetch( { path: `${ c.path }?screen=${ encodeURIComponent( c.screen ) }&set=${ c.set }`, method: 'DELETE' } ), await p.evaluate( () => window.corinthianTable ) );
await p.reload();
check( !! ( await p.$( 'th#title .corinthian-resize' ) ), 'resize handle present on Title' );
await p.$eval( 'th#title .corinthian-resize', ( h ) => h.scrollIntoView( { block: 'nearest', inline: 'nearest' } ) );
const hit = await p.$eval( 'th#title .corinthian-resize', ( h ) => { const r = h.getBoundingClientRect(); return document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 ) === h; } );
check( hit, 'handle is the element under the pointer (not clipped)' );
const before = await width( p, 'title' );
const box = await p.$eval( 'th#title .corinthian-resize', ( h ) => { const r = h.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; } );
const saved = p.waitForResponse( ( r ) => r.url().includes( 'corinthian/v1/widths' ) && r.request().method() === 'POST' );
await p.mouse.move( box.x, box.y );
await p.mouse.down();
await p.mouse.move( box.x - 150, box.y, { steps: 8 } );
await p.mouse.up();
const res = await saved;
check( res.status() === 200, `save returned ${ res.status() }` );
const dragged = await width( p, 'title' );
check( dragged < before - 100, `drag shrank Title ${ before } -> ${ dragged }` );
check( ! p.url().includes( 'orderby' ), 'drag did not trigger a sort' );

await p.reload();
const reloaded = await width( p, 'title' );
check( Math.abs( reloaded - dragged ) <= 2, `width persisted after reload (${ reloaded })` );

const other = await browser_login( 'editor2', 'editor2pass' );
await other.p.goto( url );
const theirs = await width( other.p, 'title' );
check( Math.abs( theirs - before ) <= 2, `other user unaffected (${ theirs } vs original ${ before })` );

// Through apiFetch so the request carries a valid nonce and reaches our own check.
const control = await other.p.evaluate( ( c ) => wp.apiFetch( { path: c.path, method: 'POST', data: { screen: c.screen, set: c.set, widths: { title: 300 } } } ).then( () => 200, ( e ) => e.data?.status || e.code ), await other.p.evaluate( () => window.corinthianTable ) );
check( control === 200, `control: valid request from editor2 accepted (${ control })` );
const forged = await other.p.evaluate( ( c ) => wp.apiFetch( { path: c.path, method: 'POST', data: { screen: c.screen, set: 999999, widths: { title: 50 } } } ).then( () => 200, ( e ) => e.data?.status || e.code ), await other.p.evaluate( () => window.corinthianTable ) );
check( forged === 404, `unknown set rejected with our 404 (${ forged })` );
await other.p.reload();
check( Math.abs( ( await width( p, 'title' ) ) - reloaded ) <= 2, 'editor2 saving widths left admin untouched' );
await other.p.evaluate( ( c ) => wp.apiFetch( { path: `${ c.path }?screen=${ encodeURIComponent( c.screen ) }&set=${ c.set }`, method: 'DELETE' } ), await other.p.evaluate( () => window.corinthianTable ) );

await Promise.all( [ p.waitForNavigation(), p.dblclick( 'th#title .corinthian-resize' ) ] );
const afterReset = await width( p, 'title' );
check( Math.abs( afterReset - before ) <= 2, `double-click reset (${ afterReset })` );

await b.close();
await other.b.close();
// Leave Pages as we found it: other tests expect no sets there.
wp( `eval '$r = \\Corinthian\\Plugin::instance()->sets; foreach ( $r->for_screen( "post:page" ) as $s ) { if ( "Reversed" === $s->title ) { $r->delete( $s->id ); } }'` );
process.exit( fail ? 1 : 0 );
