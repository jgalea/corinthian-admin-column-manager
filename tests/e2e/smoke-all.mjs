import { chromium } from '@playwright/test';
const pages = [ 'edit.php', 'edit.php?post_type=page', 'upload.php?mode=list', 'users.php', 'edit-comments.php', 'edit-tags.php?taxonomy=category', 'edit-tags.php?taxonomy=post_tag', 'options-general.php?page=corinthian', 'index.php' ];
const b = await chromium.launch();
const p = await b.newPage();
const jsErrors = [];
p.on( 'pageerror', ( e ) => jsErrors.push( e.message ) );
await p.goto( 'http://localhost:8930/wp-login.php' );
await p.fill( '#user_login', 'admin' );
await p.fill( '#user_pass', 'password' );
await Promise.all( [ p.waitForNavigation(), p.click( '#wp-submit' ) ] );
let bad = 0;
for ( const path of pages ) {
	const res = await p.goto( `http://localhost:8930/wp-admin/${ path }` );
	const php = ( await p.textContent( 'body' ) ).match( /(Fatal error|Warning|Notice|Deprecated):/g ) || [];
	if ( res.status() !== 200 || php.length ) bad++;
	console.log( `${ res.status() } ${ path.padEnd( 40 ) } php-on-page=${ php.length }` );
}
console.log( 'js errors:', jsErrors.length, jsErrors.slice( 0, 3 ) );
await b.close();
process.exit( bad || jsErrors.length ? 1 : 0 );
