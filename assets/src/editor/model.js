export const slug = ( value ) =>
	String( value )
		.toLowerCase()
		.replace( /[^a-z0-9_]/g, '_' )
		.replace( /^_+|_+$/g, '' ) || 'column';

export const uniqueId = ( base, columns ) => {
	const taken = new Set( columns.map( ( c ) => c.id ) );
	const root = slug( base );
	let id = root;
	let n = 2;
	while ( taken.has( id ) ) {
		id = `${ root }_${ n++ }`;
	}
	return id;
};

export const optionList = ( options ) =>
	Array.isArray( options )
		? options.map( ( o ) =>
				typeof o === 'object' ? o : { value: o, label: String( o ) }
		  )
		: Object.entries( options || {} ).map( ( [ value, label ] ) => ( {
				value,
				label,
		  } ) );

export const defaultSettings = ( fields ) => {
	const out = {};
	( fields || [] ).forEach( ( f ) => {
		out[ f.name ] = f.default ?? '';
	} );
	return out;
};

export const nativeColumn = ( native, columns ) => ( {
	id: uniqueId( native.key, columns ),
	type: 'native',
	label: native.label,
	width: null,
	width_unit: 'px',
	settings: { key: native.key },
} );

export const typedColumn = ( type, columns ) => ( {
	id: uniqueId( type.key, columns ),
	type: type.key,
	label: type.label,
	width: null,
	width_unit: 'px',
	settings: defaultSettings( type.settings ),
} );

export const blankSet = ( screen, columns = [] ) => ( {
	id: 0,
	screen,
	title: '',
	columns,
	settings: {
		roles: [],
		users: [],
		fit_width: false,
		sticky_header: false,
		resize_columns: true,
	},
	position: 0,
} );

export const normalise = ( set ) => ( {
	...set,
	settings: {
		roles: [],
		fit_width: false,
		sticky_header: false,
		resize_columns: true,
		...( Array.isArray( set.settings ) ? {} : set.settings ),
	},
	columns: ( set.columns || [] ).map( ( c ) => ( {
		...c,
		settings: Array.isArray( c.settings ) ? {} : c.settings || {},
	} ) ),
} );

export const move = ( list, from, to ) => {
	if ( to < 0 || to >= list.length || from === to ) {
		return list;
	}
	const next = list.slice();
	next.splice( to, 0, next.splice( from, 1 )[ 0 ] );
	return next;
};
