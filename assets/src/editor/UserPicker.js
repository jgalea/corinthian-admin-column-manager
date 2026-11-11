import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, TextControl, Spinner } from '@wordpress/components';

const lookup = ( query ) =>
	apiFetch( {
		path: `/wp/v2/users?context=edit&per_page=10&_fields=id,name&${ query }`,
	} );

export default function UserPicker( { ids, onChange } ) {
	const [ names, setNames ] = useState( {} );
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		const missing = ids.filter( ( id ) => ! names[ id ] );
		if ( ! missing.length ) {
			return;
		}
		lookup( `include=${ missing.join( ',' ) }` )
			.then( ( users ) =>
				setNames( ( n ) => ( {
					...n,
					...Object.fromEntries(
						users.map( ( u ) => [ u.id, u.name ] )
					),
				} ) )
			)
			.catch( () => {} );
	}, [ ids ] ); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect( () => {
		if ( search.trim().length < 2 ) {
			setResults( [] );
			return undefined;
		}
		setBusy( true );
		const timer = setTimeout( () => {
			lookup( `search=${ encodeURIComponent( search.trim() ) }` )
				.then( ( users ) => {
					setNames( ( n ) => ( {
						...n,
						...Object.fromEntries(
							users.map( ( u ) => [ u.id, u.name ] )
						),
					} ) );
					setResults(
						users.filter( ( u ) => ! ids.includes( u.id ) )
					);
				} )
				.catch( () => setResults( [] ) )
				.finally( () => setBusy( false ) );
		}, 250 );
		return () => clearTimeout( timer );
	}, [ search, ids ] );

	return (
		<fieldset className="corinthian-users">
			<legend>
				{ __(
					'Show to specific users',
					'corinthian-admin-column-manager'
				) }
			</legend>
			{ ids.length > 0 && (
				<ul className="corinthian-users__chips">
					{ ids.map( ( id ) => (
						<li key={ id } className="corinthian-users__chip">
							<span>{ names[ id ] || `#${ id }` }</span>
							<Button
								size="small"
								variant="tertiary"
								label={ `${ __(
									'Remove',
									'corinthian-admin-column-manager'
								) } ${ names[ id ] || id }` }
								onClick={ () =>
									onChange( ids.filter( ( x ) => x !== id ) )
								}
							>
								&times;
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Find a user', 'corinthian-admin-column-manager' ) }
				value={ search }
				onChange={ setSearch }
			/>
			{ busy && <Spinner /> }
			{ results.length > 0 && (
				<ul className="corinthian-users__results">
					{ results.map( ( user ) => (
						<li key={ user.id }>
							<Button
								variant="secondary"
								onClick={ () => {
									onChange( [ ...ids, user.id ] );
									setSearch( '' );
									setResults( [] );
								} }
							>
								{ user.name }
							</Button>
						</li>
					) ) }
				</ul>
			) }
		</fieldset>
	);
}
