import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
import os from 'node:os';
import path from 'node:path';

const base = process.env.WP_BASE_URL || 'http://localhost:8930';
const shots = path.join( os.homedir(), 'code/artifacts/corinthian' );
const editorUrl = `${ base }/wp-admin/options-general.php?page=corinthian`;

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );

const openPages = async () => {
	await page.goto( editorUrl );
	await page.waitForSelector( '.corinthian-app' );
	await page.getByLabel( 'List screen' ).selectOption( { label: 'Pages' } );
};

await openPages();

// Native discovery may need a moment on a fresh site.
const create = page.getByRole( 'button', { name: 'Create set from current columns' } );
await page.waitForFunction( () => {
	const b = [ ...document.querySelectorAll( 'button' ) ].find( ( x ) => x.textContent.includes( 'Create set from current columns' ) );
	return ! b || ! b.disabled;
} );
assert.ok( await create.isVisible(), 'empty state with create button should show for Pages (delete leftover sets first)' );
await create.click();
await page.locator( '.components-notice__content', { hasText: 'Set saved.' } ).waitFor();
const before = await page.locator( '.corinthian-row' ).count();
assert.ok( before >= 3, `expected native rows, got ${ before }` );
console.log( 'created set with', before, 'native columns' );

await page.getByLabel( 'Column to add' ).selectOption( { label: 'Custom field' } );
await page.getByRole( 'button', { name: 'Add column', exact: true } ).click();
const row = page.locator( '.corinthian-row' ).last();
await row.getByLabel( 'Field', { exact: true } ).fill( 'priority' );
await row.getByLabel( 'Show as' ).selectOption( 'number' );
await row.getByLabel( /^Width of/ ).fill( '120' );
await row.getByLabel( /^Width unit of/ ).selectOption( 'px' );

for ( let i = before; i > 1; i-- ) {
	await page.locator( '.corinthian-row' ).nth( i ).getByRole( 'button', { name: 'Move up' } ).click();
}
const labels = await page.locator( '.corinthian-row__label' ).allTextContents();
assert.equal( labels[ 1 ], 'Custom field', `Custom field should be second: ${ labels }` );

await page.screenshot( { path: path.join( shots, 'editor-1440.png' ), fullPage: true } );
await page.getByRole( 'button', { name: 'Save set' } ).click();
await page.locator( '.components-notice__content', { hasText: 'Set saved.' } ).waitFor();

// Persisted after reload.
await openPages();
const reloaded = await page.locator( '.corinthian-row__label' ).allTextContents();
assert.equal( reloaded[ 1 ], 'Custom field', 'order persisted' );
await page.locator( '.corinthian-row' ).nth( 1 ).getByRole( 'button', { name: /^Settings for/ } ).click();
const panel = page.locator( '.corinthian-row' ).nth( 1 );
assert.equal( await panel.getByLabel( 'Field', { exact: true } ).inputValue(), 'priority' );
assert.equal( await panel.getByLabel( 'Show as' ).inputValue(), 'number' );
assert.equal( await panel.getByLabel( /^Width of/ ).inputValue(), '120' );
console.log( 'persisted after reload' );

// Mobile screenshot.
await page.setViewportSize( { width: 390, height: 844 } );
await page.screenshot( { path: path.join( shots, 'editor-390.png' ), fullPage: true } );
const overflow = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
assert.ok( overflow <= 0, `horizontal overflow at 390: ${ overflow }` );
await page.setViewportSize( { width: 320, height: 700 } );
const overflow320 = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
assert.ok( overflow320 <= 0, `horizontal overflow at 320: ${ overflow320 }` );
await page.setViewportSize( { width: 1440, height: 900 } );

// List screen shows the column at 120px.
await page.goto( `${ base }/wp-admin/edit.php?post_type=page` );
const widths = await page.evaluate( () =>
	[ ...document.querySelectorAll( '.wp-list-table thead th' ) ].map( ( t ) => ( {
		text: t.innerText.trim(),
		width: Math.round( t.getBoundingClientRect().width ),
	} ) )
);
const hit = widths.find( ( w ) => w.text.startsWith( 'Custom field' ) );
assert.ok( hit, `Custom field header missing: ${ JSON.stringify( widths ) }` );
assert.equal( hit.width, 120, `width ${ hit.width }` );
console.log( 'list header', JSON.stringify( hit ) );

// Clean up: delete the set via the Modal.
await openPages();
await page.getByRole( 'button', { name: 'Delete set' } ).click();
await page.getByRole( 'dialog' ).getByRole( 'button', { name: 'Delete set' } ).click();
await page.locator( '.components-notice__content', { hasText: 'Set deleted.' } ).waitFor();
await page.getByText( 'No column sets yet' ).waitFor();
console.log( 'deleted' );

assert.deepEqual( errors, [], `page errors: ${ errors }` );
await browser.close();
console.log( 'editor e2e: all assertions passed' );
