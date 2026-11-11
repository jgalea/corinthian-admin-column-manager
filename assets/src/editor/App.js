import {
	useState,
	useEffect,
	useMemo,
	useCallback,
	useRef,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';
import { chevronLeft, chevronRight } from '@wordpress/icons';
import ColumnRow from './ColumnRow';
import UserPicker from './UserPicker';
import { blankSet, nativeColumn, typedColumn, normalise, move } from './model';

const cfg = window.corinthianEditor || {
	namespace: 'corinthian/v1',
	roles: [],
	initial: {},
};
const api = ( path, options = {} ) =>
	apiFetch( { path: `/${ cfg.namespace }${ path }`, ...options } );

function AddColumn( { screen, onAdd } ) {
	const [ value, setValue ] = useState( '' );
	const groups = useMemo( () => {
		const out = {};
		screen.types.forEach( ( t ) => {
			( out[
				t.group || __( 'Other', 'corinthian-admin-column-manager' )
			] ||= [] ).push( { value: `type:${ t.key }`, label: t.label } );
		} );
		return out;
	}, [ screen ] );

	const add = () => {
		if ( value ) {
			onAdd( value );
			setValue( '' );
		}
	};

	return (
		<div className="corinthian-add">
			<label
				className="corinthian-add__field"
				htmlFor="corinthian-add-column"
			>
				<span className="corinthian-add__label">
					{ __( 'Column to add', 'corinthian-admin-column-manager' ) }
				</span>
				<select
					id="corinthian-add-column"
					className="corinthian-select"
					value={ value }
					onChange={ ( e ) => setValue( e.target.value ) }
				>
					<option value="">
						{ __(
							'Choose a column',
							'corinthian-admin-column-manager'
						) }
					</option>
					{ screen.native.length > 0 && (
						<optgroup
							label={ __(
								'Existing columns',
								'corinthian-admin-column-manager'
							) }
						>
							{ screen.native.map( ( n ) => (
								<option
									key={ n.key }
									value={ `native:${ n.key }` }
								>
									{ n.label || n.key }
								</option>
							) ) }
						</optgroup>
					) }
					{ Object.entries( groups ).map( ( [ group, items ] ) => (
						<optgroup key={ group } label={ group }>
							{ items.map( ( i ) => (
								<option key={ i.value } value={ i.value }>
									{ i.label }
								</option>
							) ) }
						</optgroup>
					) ) }
				</select>
			</label>
			<Button variant="secondary" onClick={ add } disabled={ ! value }>
				{ __( 'Add column', 'corinthian-admin-column-manager' ) }
			</Button>
		</div>
	);
}

const NATIVE_SORTABLE = [
	'title',
	'date',
	'author',
	'name',
	'username',
	'email',
	'slug',
	'posts',
];

function SetEditor( {
	screen,
	draft,
	setDraft,
	dirty,
	saving,
	onSave,
	onDelete,
	onDuplicate,
} ) {
	const readOnly = !! draft.settings.readonly;
	const [ expanded, setExpanded ] = useState( {} );
	const [ dragFrom, setDragFrom ] = useState( null );
	const [ dropAt, setDropAt ] = useState( null );
	const [ announce, setAnnounce ] = useState( '' );
	const typeMap = useMemo(
		() => Object.fromEntries( screen.types.map( ( t ) => [ t.key, t ] ) ),
		[ screen ]
	);
	const metaKeys = [
		...new Set(
			draft.columns.map( ( c ) => c.settings?.meta_key ).filter( Boolean )
		),
	];

	const patch = ( changes ) => setDraft( ( d ) => ( { ...d, ...changes } ) );
	const patchSettings = ( changes ) =>
		setDraft( ( d ) => ( {
			...d,
			settings: { ...d.settings, ...changes },
		} ) );
	// Panels with slot="toggles" sit in the toggle list; the rest follow the columns card.
	const panels = applyFilters( 'corinthian.editor.setPanels', [], {
		draft,
		patchSettings,
		screen,
	} );
	const togglePanels = panels.filter( ( p ) => p.props?.slot === 'toggles' );
	const blockPanels = panels.filter( ( p ) => p.props?.slot !== 'toggles' );
	const patchColumn = ( id, changes ) =>
		setDraft( ( d ) => ( {
			...d,
			columns: d.columns.map( ( c ) =>
				c.id === id ? { ...c, ...changes } : c
			),
		} ) );

	const reorder = ( from, to ) => {
		setDraft( ( d ) => ( { ...d, columns: move( d.columns, from, to ) } ) );
		const col = draft.columns[ from ];
		setAnnounce(
			sprintf(
				/* translators: 1: column label, 2: position, 3: total */
				__(
					'%1$s moved to position %2$d of %3$d',
					'corinthian-admin-column-manager'
				),
				col.label,
				to + 1,
				draft.columns.length
			)
		);
	};

	const add = ( encoded ) => {
		const [ kind, key ] = [
			encoded.slice( 0, encoded.indexOf( ':' ) ),
			encoded.slice( encoded.indexOf( ':' ) + 1 ),
		];
		setDraft( ( d ) => {
			let col = null;
			if ( kind === 'native' ) {
				const n = screen.native.find( ( x ) => x.key === key );
				col = n && nativeColumn( n, d.columns );
			} else {
				const t = typeMap[ key ];
				col = t && typedColumn( t, d.columns );
			}
			if ( ! col ) {
				return d;
			}
			setExpanded( ( e ) => ( { ...e, [ col.id ]: kind !== 'native' } ) );
			return { ...d, columns: [ ...d.columns, col ] };
		} );
	};

	const sortOptions = draft.columns.flatMap( ( c ) => {
		if ( c.type === 'native' ) {
			return NATIVE_SORTABLE.includes( c.settings?.key )
				? [
						{
							value: c.settings.key,
							label: c.label || c.settings.key,
						},
				  ]
				: [];
		}
		return typeMap[ c.type ]?.sortable && c.settings?.sortable !== false
			? [
					{
						value: `corinthian-${ c.id }`,
						label: c.label || typeMap[ c.type ].label,
					},
			  ]
			: [];
	} );
	const users = draft.settings.users || [];
	const everyone = draft.settings.roles.length === 0 && users.length === 0;

	const toggleRole = ( role, on ) =>
		patchSettings( {
			roles: on
				? [ ...draft.settings.roles, role ]
				: draft.settings.roles.filter( ( r ) => r !== role ),
		} );

	return (
		<form
			className="corinthian-set"
			onSubmit={ ( e ) => {
				e.preventDefault();
				onSave();
			} }
		>
			{ readOnly && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This set is defined in code. Duplicate it to get an editable copy.',
						'corinthian-admin-column-manager'
					) }
				</Notice>
			) }
			<fieldset className="corinthian-fieldset" disabled={ readOnly }>
				<div className="corinthian-card">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __(
							'Set name',
							'corinthian-admin-column-manager'
						) }
						value={ draft.title }
						onChange={ ( title ) => patch( { title } ) }
					/>
					<fieldset className="corinthian-roles">
						<legend>
							{ __(
								'Show to roles',
								'corinthian-admin-column-manager'
							) }
						</legend>
						<div className="corinthian-roles__list">
							{ cfg.roles.map( ( r ) => (
								<CheckboxControl
									__nextHasNoMarginBottom
									key={ r.value }
									label={ r.label }
									checked={ draft.settings.roles.includes(
										r.value
									) }
									onChange={ ( on ) =>
										toggleRole( r.value, on )
									}
								/>
							) ) }
						</div>
					</fieldset>
					<UserPicker
						ids={ users }
						onChange={ ( next ) =>
							patchSettings( { users: next } )
						}
					/>
					<p className="corinthian-muted">
						{ everyone
							? __(
									'Everyone can use this set.',
									'corinthian-admin-column-manager'
							  )
							: __(
									'Only the selected roles and users can use this set.',
									'corinthian-admin-column-manager'
							  ) }
					</p>
					<div className="corinthian-prefs">
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="number"
							min={ 0 }
							max={ 999 }
							label={ __(
								'Rows per page',
								'corinthian-admin-column-manager'
							) }
							help={ __(
								'0 uses the WordPress setting. A number picked in Screen Options takes priority.',
								'corinthian-admin-column-manager'
							) }
							value={ draft.settings.per_page || 0 }
							onChange={ ( v ) =>
								patchSettings( {
									per_page: Math.max(
										0,
										parseInt( v, 10 ) || 0
									),
								} )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Default sort',
								'corinthian-admin-column-manager'
							) }
							value={ draft.settings.default_orderby || '' }
							options={ [
								{
									value: '',
									label: __(
										'WordPress default',
										'corinthian-admin-column-manager'
									),
								},
								...sortOptions,
							] }
							onChange={ ( v ) =>
								patchSettings( { default_orderby: v } )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Sort direction',
								'corinthian-admin-column-manager'
							) }
							value={ draft.settings.default_order || 'asc' }
							disabled={ ! draft.settings.default_orderby }
							options={ [
								{
									value: 'asc',
									label: __(
										'Ascending',
										'corinthian-admin-column-manager'
									),
								},
								{
									value: 'desc',
									label: __(
										'Descending',
										'corinthian-admin-column-manager'
									),
								},
							] }
							onChange={ ( v ) =>
								patchSettings( { default_order: v } )
							}
						/>
					</div>
					<div className="corinthian-toggles">
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Sticky header',
								'corinthian-admin-column-manager'
							) }
							checked={ !! draft.settings.sticky_header }
							onChange={ ( v ) =>
								patchSettings( { sticky_header: v } )
							}
						/>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Fit columns to the page width',
								'corinthian-admin-column-manager'
							) }
							help={ __(
								'Shows every column without a horizontal scrollbar.',
								'corinthian-admin-column-manager'
							) }
							checked={ !! draft.settings.fit_width }
							onChange={ ( v ) =>
								patchSettings( { fit_width: v } )
							}
						/>
						{ togglePanels }
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Allow column resizing',
								'corinthian-admin-column-manager'
							) }
							checked={ draft.settings.resize_columns !== false }
							onChange={ ( v ) =>
								patchSettings( { resize_columns: v } )
							}
						/>
					</div>
				</div>

				<div className="corinthian-card">
					<h2 className="corinthian-heading">
						{ __( 'Columns', 'corinthian-admin-column-manager' ) }
					</h2>
					{ draft.columns.length === 0 ? (
						<p className="corinthian-muted">
							{ __(
								'This set has no columns yet.',
								'corinthian-admin-column-manager'
							) }
						</p>
					) : (
						<ol className="corinthian-rows">
							{ draft.columns.map( ( column, index ) => (
								<ColumnRow
									key={ column.id }
									column={ column }
									index={ index }
									count={ draft.columns.length }
									typeInfo={ typeMap[ column.type ] }
									typeLabel={
										column.type === 'native'
											? __(
													'Existing column',
													'corinthian-admin-column-manager'
											  )
											: typeMap[ column.type ]?.label ||
											  column.type
									}
									expanded={ !! expanded[ column.id ] }
									dragging={ dragFrom === index }
									dropTarget={
										dropAt === index &&
										dragFrom !== null &&
										dragFrom !== index
									}
									onToggle={ () =>
										setExpanded( ( e ) => ( {
											...e,
											[ column.id ]: ! e[ column.id ],
										} ) )
									}
									onChange={ ( changes ) =>
										patchColumn( column.id, changes )
									}
									onMove={ reorder }
									onRemove={ () =>
										setDraft( ( d ) => ( {
											...d,
											columns: d.columns.filter(
												( c ) => c.id !== column.id
											),
										} ) )
									}
									onDragStart={ ( e, i ) => {
										if ( readOnly ) {
											e.preventDefault();
											return;
										}
										e.dataTransfer.effectAllowed = 'move';
										e.dataTransfer.setData(
											'text/plain',
											String( i )
										);
										setDragFrom( i );
									} }
									onDragOver={ ( e, i ) => {
										if ( dragFrom === null ) {
											return;
										}
										e.preventDefault();
										setDropAt( i );
									} }
									onDrop={ ( e, i ) => {
										e.preventDefault();
										if ( dragFrom !== null ) {
											reorder( dragFrom, i );
										}
										setDragFrom( null );
										setDropAt( null );
									} }
									onDragEnd={ () => {
										setDragFrom( null );
										setDropAt( null );
									} }
								/>
							) ) }
						</ol>
					) }
					<datalist id="corinthian-meta-keys">
						{ metaKeys.map( ( k ) => (
							<option key={ k } value={ k } />
						) ) }
					</datalist>
					<div className="screen-reader-text" aria-live="polite">
						{ announce }
					</div>
					<AddColumn screen={ screen } onAdd={ add } />
				</div>

				{ blockPanels }
			</fieldset>

			<div className="corinthian-footer">
				{ ! readOnly && (
					<Button
						variant="primary"
						type="submit"
						isBusy={ saving }
						disabled={ saving || ( ! dirty && draft.id !== 0 ) }
					>
						{ __( 'Save set', 'corinthian-admin-column-manager' ) }
					</Button>
				) }
				{ dirty && (
					<span className="corinthian-dirty">
						{ __(
							'Unsaved changes',
							'corinthian-admin-column-manager'
						) }
					</span>
				) }
				<span className="corinthian-footer__spacer" />
				{ draft.id !== 0 && (
					<Button
						variant="secondary"
						onClick={ onDuplicate }
						disabled={ dirty }
					>
						{ __(
							'Duplicate set',
							'corinthian-admin-column-manager'
						) }
					</Button>
				) }
				{ ! readOnly && (
					<Button
						variant="tertiary"
						isDestructive
						onClick={ onDelete }
					>
						{ draft.id
							? __(
									'Delete set',
									'corinthian-admin-column-manager'
							  )
							: __(
									'Discard set',
									'corinthian-admin-column-manager'
							  ) }
					</Button>
				) }
			</div>
		</form>
	);
}

export default function App() {
	const [ screens, setScreens ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ screenKey, setScreenKey ] = useState( '' );
	const [ draft, setDraft ] = useState( null );
	const [ saved, setSaved ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const [ pending, setPending ] = useState( null );
	const [ discovering, setDiscovering ] = useState( false );
	const [ duplicating, setDuplicating ] = useState( null );
	const discovered = useRef( new Set() );
	const initialised = useRef( false );

	const screen = screens?.find( ( s ) => s.key === screenKey );
	const dirty = !! draft && JSON.stringify( draft ) !== saved;

	const load = useCallback( async () => {
		const data = await api( '/screens' );
		setScreens( data );
		return data;
	}, [] );

	const open = useCallback( ( set ) => {
		const next = normalise( set );
		setDraft( next );
		setSaved( JSON.stringify( next ) );
		setNotice( null );
	}, [] );

	useEffect( () => {
		load()
			.then( ( data ) => {
				const wanted =
					data.find( ( s ) => s.key === cfg.initial?.screen ) ||
					data[ 0 ];
				if ( ! wanted ) {
					return;
				}
				setScreenKey( wanted.key );
				const set =
					wanted.sets.find( ( s ) => s.id === cfg.initial?.set ) ||
					wanted.sets[ 0 ];
				if ( set ) {
					open( set );
				}
				initialised.current = true;
			} )
			.catch( ( e ) =>
				setError(
					e.message ||
						__(
							'Could not load list screens.',
							'corinthian-admin-column-manager'
						)
				)
			);
	}, [ load, open ] );

	useEffect( () => {
		if (
			! screen ||
			screen.native.length > 0 ||
			discovered.current.has( screen.key )
		) {
			return;
		}
		discovered.current.add( screen.key );
		setDiscovering( true );
		fetch( screen.url, { credentials: 'same-origin' } )
			.catch( () => {} )
			.then( () => load() )
			.catch( () => {} )
			.finally( () => setDiscovering( false ) );
	}, [ screen, load ] );

	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}
		const guard = ( e ) => {
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', guard );
		return () => window.removeEventListener( 'beforeunload', guard );
	}, [ dirty ] );

	const guarded = ( action ) =>
		dirty ? setPending( () => action ) : action();

	const pickScreen = ( key ) =>
		guarded( () => {
			const next = screens.find( ( s ) => s.key === key );
			setScreenKey( key );
			setNotice( null );
			if ( next?.sets[ 0 ] ) {
				open( next.sets[ 0 ] );
			} else {
				setDraft( null );
				setSaved( '' );
			}
		} );

	const syncSet = ( set ) =>
		setScreens( ( list ) =>
			list.map( ( s ) =>
				s.key === set.screen
					? {
							...s,
							sets: s.sets.some( ( x ) => x.id === set.id )
								? s.sets.map( ( x ) =>
										x.id === set.id ? set : x
								  )
								: [ ...s.sets, set ],
					  }
					: s
			)
		);

	const save = async ( override ) => {
		const body = override || draft;
		setSaving( true );
		try {
			const result = await api(
				body.id ? `/sets/${ body.id }` : '/sets',
				{
					method: body.id ? 'PUT' : 'POST',
					data: body,
				}
			);
			syncSet( result );
			open( result );
			setNotice( {
				status: 'success',
				text: __( 'Set saved.', 'corinthian-admin-column-manager' ),
			} );
		} catch ( e ) {
			setNotice( {
				status: 'error',
				text: sprintf(
					/* translators: %s: error message */
					__(
						'Could not save the set: %s',
						'corinthian-admin-column-manager'
					),
					e.message ||
						__( 'unknown error', 'corinthian-admin-column-manager' )
				),
			} );
		} finally {
			setSaving( false );
		}
	};

	const createFromCurrent = () => {
		const columns = screen.native.reduce(
			( acc, n ) => [ ...acc, nativeColumn( n, acc ) ],
			[]
		);
		save( {
			...blankSet( screen.key, columns ),
			title: __( 'Default', 'corinthian-admin-column-manager' ),
		} );
	};

	const addSet = () =>
		guarded( () => {
			const next = blankSet( screen.key );
			next.title = __( 'New set', 'corinthian-admin-column-manager' );
			setDraft( next );
			setSaved( '' );
			setNotice( null );
		} );

	const duplicate = async () => {
		const target = duplicating;
		setDuplicating( null );
		setSaving( true );
		try {
			const copy = await api( `/sets/${ draft.id }/duplicate`, {
				method: 'POST',
				data: { screen: target.screen, title: target.title },
			} );
			syncSet( copy );
			setScreenKey( copy.screen );
			open( copy );
			setNotice( {
				status: 'success',
				text: __(
					'Set duplicated.',
					'corinthian-admin-column-manager'
				),
			} );
		} catch ( e ) {
			setNotice( {
				status: 'error',
				text: sprintf(
					/* translators: %s: error message */
					__(
						'Could not duplicate the set: %s',
						'corinthian-admin-column-manager'
					),
					e.message ||
						__( 'unknown error', 'corinthian-admin-column-manager' )
				),
			} );
		} finally {
			setSaving( false );
		}
	};

	const moveSet = ( direction ) =>
		guarded( async () => {
			const stored = screen.sets.filter(
				( s ) => ! s.settings?.readonly
			);
			const from = stored.findIndex( ( s ) => s.id === draft.id );
			const to = from + direction;
			if ( from < 0 || to < 0 || to >= stored.length ) {
				return;
			}
			const order = stored.slice();
			order.splice( to, 0, order.splice( from, 1 )[ 0 ] );
			try {
				await Promise.all(
					order.map( ( s, index ) =>
						s.position === index
							? null
							: api( `/sets/${ s.id }`, {
									method: 'PUT',
									data: { position: index },
							  } )
					)
				);
				const data = await load();
				const fresh = data
					.find( ( s ) => s.key === screenKey )
					?.sets.find( ( s ) => s.id === draft.id );
				if ( fresh ) {
					open( fresh );
				}
			} catch ( e ) {
				setNotice( {
					status: 'error',
					text: sprintf(
						/* translators: %s: error message */
						__(
							'Could not reorder the sets: %s',
							'corinthian-admin-column-manager'
						),
						e.message ||
							__(
								'unknown error',
								'corinthian-admin-column-manager'
							)
					),
				} );
			}
		} );

	const removeSet = async () => {
		setConfirmDelete( false );
		if ( ! draft.id ) {
			const first = screen.sets[ 0 ];
			if ( first ) {
				open( first );
			} else {
				setDraft( null );
			}
			return;
		}
		try {
			await api( `/sets/${ draft.id }`, { method: 'DELETE' } );
			const remaining = screen.sets.filter( ( s ) => s.id !== draft.id );
			setScreens( ( list ) =>
				list.map( ( s ) =>
					s.key === screen.key ? { ...s, sets: remaining } : s
				)
			);
			if ( remaining[ 0 ] ) {
				open( remaining[ 0 ] );
			} else {
				setDraft( null );
				setSaved( '' );
			}
			setNotice( {
				status: 'success',
				text: __( 'Set deleted.', 'corinthian-admin-column-manager' ),
			} );
		} catch ( e ) {
			setNotice( {
				status: 'error',
				text: sprintf(
					/* translators: %s: error message */
					__(
						'Could not delete the set: %s',
						'corinthian-admin-column-manager'
					),
					e.message ||
						__( 'unknown error', 'corinthian-admin-column-manager' )
				),
			} );
		}
	};

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! screens ) {
		return <Spinner />;
	}
	if ( ! screen ) {
		return (
			<p>
				{ __(
					'No list screens are available.',
					'corinthian-admin-column-manager'
				) }
			</p>
		);
	}

	const tabSets =
		draft && ! draft.id ? [ ...screen.sets, draft ] : screen.sets;
	const storedSets = screen.sets.filter( ( s ) => ! s.settings?.readonly );
	const storedIndex = draft?.id
		? storedSets.findIndex( ( s ) => s.id === draft.id )
		: -1;

	return (
		<div className="corinthian-app">
			<div className="corinthian-bar">
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __(
						'List screen',
						'corinthian-admin-column-manager'
					) }
					value={ screenKey }
					options={ screens.map( ( s ) => ( {
						value: s.key,
						label: s.label,
					} ) ) }
					onChange={ pickScreen }
				/>
				<Button
					variant="link"
					href={ screen.url }
					className="corinthian-bar__link"
				>
					{ __( 'View list', 'corinthian-admin-column-manager' ) }
				</Button>
			</div>

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }

			{ discovering && <Spinner /> }

			{ tabSets.length > 0 && (
				<div
					className="corinthian-tabs"
					role="tablist"
					aria-label={ __(
						'Column sets',
						'corinthian-admin-column-manager'
					) }
				>
					{ tabSets.map( ( s ) => {
						const active = draft && s.id === draft.id;
						return (
							<button
								key={ s.id || 'new' }
								type="button"
								role="tab"
								aria-selected={ !! active }
								className={
									'corinthian-tab' +
									( active ? ' is-active' : '' )
								}
								onClick={ () =>
									! active && guarded( () => open( s ) )
								}
							>
								{ ( active ? draft.title : s.title ) ||
									__(
										'Untitled set',
										'corinthian-admin-column-manager'
									) }
							</button>
						);
					} ) }
					<Button
						variant="secondary"
						size="compact"
						onClick={ addSet }
					>
						{ __( 'Add set', 'corinthian-admin-column-manager' ) }
					</Button>
					{ storedIndex >= 0 && storedSets.length > 1 && (
						<span className="corinthian-tabs__move">
							<Button
								size="compact"
								icon={ chevronLeft }
								label={ __(
									'Move set earlier',
									'corinthian-admin-column-manager'
								) }
								disabled={ storedIndex === 0 }
								onClick={ () => moveSet( -1 ) }
							/>
							<Button
								size="compact"
								icon={ chevronRight }
								label={ __(
									'Move set later',
									'corinthian-admin-column-manager'
								) }
								disabled={
									storedIndex === storedSets.length - 1
								}
								onClick={ () => moveSet( 1 ) }
							/>
						</span>
					) }
				</div>
			) }

			{ draft ? (
				<SetEditor
					screen={ screen }
					draft={ draft }
					setDraft={ setDraft }
					dirty={ dirty }
					saving={ saving }
					onSave={ () => save() }
					onDelete={ () =>
						draft.id ? setConfirmDelete( true ) : removeSet()
					}
					onDuplicate={ () =>
						setDuplicating( { title: '', screen: screen.key } )
					}
				/>
			) : (
				<div className="corinthian-card corinthian-empty">
					<h2 className="corinthian-heading">
						{ __(
							'No column sets yet',
							'corinthian-admin-column-manager'
						) }
					</h2>
					<p>
						{ sprintf(
							/* translators: %s: list screen name */
							__(
								'WordPress default columns apply on the %s screen until you create a set.',
								'corinthian-admin-column-manager'
							),
							screen.label
						) }
					</p>
					<div className="corinthian-empty__actions">
						<Button
							variant="primary"
							onClick={ createFromCurrent }
							isBusy={ saving }
							disabled={
								saving ||
								discovering ||
								screen.native.length === 0
							}
						>
							{ __(
								'Create set from current columns',
								'corinthian-admin-column-manager'
							) }
						</Button>
						<Button variant="secondary" onClick={ addSet }>
							{ __(
								'Add set',
								'corinthian-admin-column-manager'
							) }
						</Button>
					</div>
				</div>
			) }

			{ confirmDelete && (
				<Modal
					title={ __(
						'Delete this set?',
						'corinthian-admin-column-manager'
					) }
					onRequestClose={ () => setConfirmDelete( false ) }
					size="small"
				>
					<p>
						{ sprintf(
							/* translators: %s: set name */
							__(
								'%s will be deleted.',
								'corinthian-admin-column-manager'
							),
							draft.title ||
								__(
									'This set',
									'corinthian-admin-column-manager'
								)
						) }
					</p>
					<div className="corinthian-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setConfirmDelete( false ) }
						>
							{ __(
								'Cancel',
								'corinthian-admin-column-manager'
							) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ removeSet }
						>
							{ __(
								'Delete set',
								'corinthian-admin-column-manager'
							) }
						</Button>
					</div>
				</Modal>
			) }

			{ duplicating && (
				<Modal
					className="corinthian-modal"
					title={ __(
						'Duplicate this set',
						'corinthian-admin-column-manager'
					) }
					onRequestClose={ () => setDuplicating( null ) }
					size="small"
				>
					<form
						onSubmit={ ( e ) => {
							e.preventDefault();
							duplicate();
						} }
					>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Name of the copy',
								'corinthian-admin-column-manager'
							) }
							placeholder={ sprintf(
								/* translators: %s: set name */
								__(
									'%s (copy)',
									'corinthian-admin-column-manager'
								),
								draft.title
							) }
							value={ duplicating.title }
							onChange={ ( title ) =>
								setDuplicating( ( d ) => ( { ...d, title } ) )
							}
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'List screen',
								'corinthian-admin-column-manager'
							) }
							value={ duplicating.screen }
							options={ screens.map( ( s ) => ( {
								value: s.key,
								label: s.label,
							} ) ) }
							onChange={ ( key ) =>
								setDuplicating( ( d ) => ( {
									...d,
									screen: key,
								} ) )
							}
						/>
						<div className="corinthian-modal-actions">
							<Button
								variant="tertiary"
								onClick={ () => setDuplicating( null ) }
							>
								{ __(
									'Cancel',
									'corinthian-admin-column-manager'
								) }
							</Button>
							<Button variant="primary" type="submit">
								{ __(
									'Duplicate set',
									'corinthian-admin-column-manager'
								) }
							</Button>
						</div>
					</form>
				</Modal>
			) }

			{ pending && (
				<Modal
					title={ __(
						'Discard unsaved changes?',
						'corinthian-admin-column-manager'
					) }
					onRequestClose={ () => setPending( null ) }
					size="small"
				>
					<div className="corinthian-modal-actions">
						<Button
							variant="tertiary"
							onClick={ () => setPending( null ) }
						>
							{ __(
								'Keep editing',
								'corinthian-admin-column-manager'
							) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ () => {
								const action = pending;
								setPending( null );
								action();
							} }
						>
							{ __(
								'Discard changes',
								'corinthian-admin-column-manager'
							) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
