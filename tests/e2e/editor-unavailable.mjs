import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';

const base = process.env.WP_BASE_URL || 'http://localhost:8930';
const editorUrl = `${ base }/wp-admin/options-general.php?page=corinthian`;
const title = 'Corinthian e2e unavailable';

const browser = await chromium.launch();
const page = await browser.newPage( { viewport: { width: 1440, height: 900 } } );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );

await page.goto( `${ base }/wp-login.php` );
await page.fill( '#user_login', 'admin' );
await page.fill( '#user_pass', 'password' );
await Promise.all( [ page.waitForNavigation(), page.click( '#wp-submit' ) ] );
const nonce = await ( await page.request.get( `${ base }/wp-admin/admin-ajax.php?action=rest-nonce` ) ).text();
const rest = async ( route, init = {} ) => {
	const res = await page.request.fetch( `${ base }/?rest_route=/corinthian/v1${ route }`, { ...init, headers: { 'X-WP-Nonce': nonce } } );
	return res.json();
};
const cleanup = async () => {
	for ( const s of await rest( '/sets' ) ) {
		if ( s.title === title ) await rest( `/sets/${ s.id }`, { method: 'DELETE' } );
	}
};

await cleanup();
try {
	const created = await rest( '/sets', {
		method: 'POST',
		data: {
			screen: 'post:post',
			title,
			columns: [
				{ id: 'ghost', type: 'missing.type', label: 'Ghost', width: null, width_unit: 'px', settings: {} },
				{ id: 'title_c', type: 'native', label: 'Title', width: null, width_unit: 'px', settings: { key: 'title' } },
			],
			settings: {},
		},
	} );
	assert.ok( created.id, `set created: ${ JSON.stringify( created ) }` );
	const hasGhost = ( set ) => ( set.columns || [] ).some( ( c ) => c.type === 'missing.type' );
	assert.ok( hasGhost( created ), 'ghost column stored via REST' );

	await page.goto( editorUrl );
	await page.waitForSelector( '.corinthian-app' );
	await page.waitForFunction( () => ! document.querySelector( '.corinthian-app .components-spinner' ) );
	await page.getByRole( 'tab', { name: title } ).click().catch( async () => {
		await page.getByText( title, { exact: true } ).first().click();
	} );
	const row = page.locator( '.corinthian-row[data-column-id="ghost"]' );
	await row.waitFor();
	assert.equal( ( await row.locator( '.corinthian-row__unavailable' ).textContent() ).trim(), 'Unavailable: its plugin is inactive' );
	const normal = page.locator( '.corinthian-row[data-column-id="title_c"] .corinthian-row__unavailable' );
	assert.equal( await normal.count(), 0, 'native column shows no unavailable label' );

	// Make a change so Save is enabled, then save.
	await page.getByLabel( 'Set name' ).fill( `${ title }` + ' ' );
	await page.getByLabel( 'Set name' ).fill( title );
	await page.getByLabel( 'Sticky header' ).check();
	await page.getByRole( 'button', { name: 'Save set' } ).click();
	await page.locator( '.components-notice__content', { hasText: 'Set saved.' } ).waitFor();

	const after = ( await rest( '/sets' ) ).find( ( s ) => s.id === created.id );
	assert.ok( after && hasGhost( after ), 'ghost column still in the set after save' );
	assert.deepEqual( errors, [], `page errors: ${ errors.join( '; ' ) }` );
	console.log( 'editor-unavailable: ok' );
} finally {
	await cleanup();
	await browser.close();
}
