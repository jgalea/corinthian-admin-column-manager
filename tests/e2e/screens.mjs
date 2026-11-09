import { chromium } from '@playwright/test';
import { execSync } from 'node:child_process';

const base = 'http://localhost:8930';
const screens = {
	users: '/wp-admin/users.php',
	media: '/wp-admin/upload.php?mode=list',
	comments: '/wp-admin/edit-comments.php',
	'term:category': '/wp-admin/edit-tags.php?taxonomy=category',
};
// Local test site: set WP_CLI_CONTAINER to your wp-env cli container.
const CLI = process.env.WP_CLI_CONTAINER || 'wp-env-cli';
const wp = ( cmd ) => execSync( `docker exec ${ CLI } wp ${ cmd }`, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'ignore' ] } ).trim();

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

const headers = () => page.$$eval( 'table.wp-list-table thead th', ( ths ) => ths.map( ( t ) => t.id ) );
let failures = 0;
for ( const [ key, path ] of Object.entries( screens ) ) {
	await page.goto( base + path );
	const before = await headers();
	const native = JSON.parse( wp( `eval-file wp-content/plugins/corinthian-admin-column-manager/tests/e2e/helpers/reverse-set.php ${ key }` ).split( '\n' ).pop() );
	const reversed = [ ...native ].reverse();
	await page.goto( base + path );
	const after = ( await headers() ).filter( ( h ) => h !== 'cb' );
	const switcher = await page.$eval( '.corinthian-switcher', ( s ) => ! s.hidden && !! s.closest( '.tablenav, .wrap' ) ).catch( () => false );
	const errors = ( await page.textContent( 'body' ) ).match( /(Fatal error|Warning|Notice|Deprecated):/g ) || [];
	const ok = JSON.stringify( after ) === JSON.stringify( reversed ) && switcher && ! errors.length && native.length > 1;
	if ( ! ok ) failures++;
	console.log( ok ? 'PASS' : 'FAIL', key, `native=${ native.length }`, 'before=', before.filter( ( h ) => h !== 'cb' ).join( ',' ), '| after=', after.join( ',' ), '| switcher=', switcher, errors.length ? errors : '' );
}
await browser.close();
process.exit( failures ? 1 : 0 );
