import { chromium } from '@playwright/test';

const base = process.env.WP_BASE_URL || 'http://localhost:8930';
const out = process.argv[ 2 ] || 'smoke.png';

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

const read = () =>
	page.evaluate( () => ( {
		cols: [ ...document.querySelectorAll( '.wp-list-table thead th' ) ].map(
			( t ) => `${ t.id }:${ Math.round( t.getBoundingClientRect().width ) }`
		),
		wrapped: !! document.querySelector( '.corinthian-scroll > .wp-list-table' ),
		values: [ ...document.querySelectorAll( 'td.column-corinthian-prio' ) ].map( ( t ) => t.innerText ),
		sortable: !! document.querySelector( 'th#corinthian-prio a' ),
		switcher: !! document.querySelector( '.corinthian-switcher' ),
		phpErrors: document.body.innerText.match( /(Fatal error|Warning|Notice|Deprecated):/g ) || [],
	} ) );

await page.goto( `${ base }/wp-admin/edit.php` );
console.log( 'default', JSON.stringify( await read() ) );
await page.goto( `${ base }/wp-admin/edit.php?orderby=corinthian-prio&order=asc` );
console.log( 'asc', JSON.stringify( ( await read() ).values ) );
await page.goto( `${ base }/wp-admin/edit.php?orderby=corinthian-prio&order=desc` );
console.log( 'desc', JSON.stringify( ( await read() ).values ) );
await page.screenshot( { path: out } );
await browser.close();
