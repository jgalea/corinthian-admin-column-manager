import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl, SelectControl, ToggleControl, Icon } from '@wordpress/components';
import { chevronUp, chevronDown, chevronRight, trash, dragHandle } from '@wordpress/icons';
import { applyFilters } from '@wordpress/hooks';
import { optionList } from './model';

function Field( { field, value, onChange, listId } ) {
	const current = value ?? field.default ?? '';
	switch ( field.type ) {
		case 'select':
			return (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					value={ current }
					options={ optionList( field.options ) }
					onChange={ onChange }
				/>
			);
		case 'toggle':
			return <ToggleControl __nextHasNoMarginBottom label={ field.label } checked={ !! current } onChange={ onChange } />;
		case 'number':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					type="number"
					label={ field.label }
					value={ current }
					onChange={ onChange }
				/>
			);
		case 'meta_key':
			return (
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ field.label }
					value={ current }
					onChange={ onChange }
					list={ listId }
					autoComplete="off"
				/>
			);
		default:
			return (
				<TextControl __nextHasNoMarginBottom __next40pxDefaultSize label={ field.label } value={ current } onChange={ onChange } />
			);
	}
}

export default function ColumnRow( {
	column,
	index,
	count,
	typeInfo,
	typeLabel,
	expanded,
	dragging,
	dropTarget,
	onToggle,
	onChange,
	onMove,
	onRemove,
	onDragStart,
	onDragOver,
	onDrop,
	onDragEnd,
} ) {
	const fields = typeInfo?.settings || [];
	const unavailable = ! typeInfo && column.type !== 'native';
	const flags = applyFilters(
		'corinthian.editor.columnToggles',
		typeInfo?.sortable ? [ { name: 'sortable', label: __( 'Sortable', 'corinthian-admin-column-manager' ), def: true } ] : [],
		typeInfo
	);
	const panelId = `corinthian-panel-${ column.id }`;
	const classes = [ 'corinthian-row' ];
	if ( dragging ) classes.push( 'is-dragging' );
	if ( dropTarget ) classes.push( 'is-drop-target' );

	return (
		<li
			className={ classes.join( ' ' ) }
			onDragOver={ ( e ) => onDragOver( e, index ) }
			onDrop={ ( e ) => onDrop( e, index ) }
			data-column-id={ column.id }
		>
			<div className="corinthian-row__main">
				<span
					className="corinthian-row__handle"
					draggable
					onDragStart={ ( e ) => onDragStart( e, index ) }
					onDragEnd={ onDragEnd }
					aria-hidden="true"
				>
					<Icon icon={ dragHandle } />
				</span>
				<Button
					icon={ chevronRight }
					size="small"
					aria-expanded={ expanded }
					aria-controls={ panelId }
					label={ sprintf(
						/* translators: %s: column label */
						__( 'Settings for %s', 'corinthian-admin-column-manager' ),
						column.label
					) }
					onClick={ onToggle }
					className={ 'corinthian-row__toggle' + ( expanded ? ' is-open' : '' ) }
				/>
				<div className="corinthian-row__title">
					<span className="corinthian-row__label">{ column.label || __( '(no label)', 'corinthian-admin-column-manager' ) }</span>
					<span className="corinthian-row__type">{ typeLabel }</span>
					{ unavailable && (
						<span className="corinthian-row__unavailable">{ __( 'Unavailable: its plugin is inactive', 'corinthian-admin-column-manager' ) }</span>
					) }
				</div>
				<div className="corinthian-row__width">
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						type="number"
						min="0"
						hideLabelFromVision
						label={ sprintf(
							/* translators: %s: column label */
							__( 'Width of %s', 'corinthian-admin-column-manager' ),
							column.label
						) }
						placeholder={ __( 'Auto', 'corinthian-admin-column-manager' ) }
						value={ column.width ?? '' }
						onChange={ ( v ) => onChange( { width: v === '' ? null : Math.max( 0, parseInt( v, 10 ) || 0 ) } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						hideLabelFromVision
						label={ sprintf(
							/* translators: %s: column label */
							__( 'Width unit of %s', 'corinthian-admin-column-manager' ),
							column.label
						) }
						value={ column.width_unit }
						options={ [
							{ value: 'px', label: 'px' },
							{ value: '%', label: '%' },
						] }
						onChange={ ( v ) => onChange( { width_unit: v } ) }
					/>
				</div>
				<div className="corinthian-row__actions">
					<Button
						icon={ chevronUp }
						label={ __( 'Move up', 'corinthian-admin-column-manager' ) }
						disabled={ index === 0 }
						onClick={ () => onMove( index, index - 1 ) }
					/>
					<Button
						icon={ chevronDown }
						label={ __( 'Move down', 'corinthian-admin-column-manager' ) }
						disabled={ index === count - 1 }
						onClick={ () => onMove( index, index + 1 ) }
					/>
					<Button icon={ trash } label={ __( 'Remove column', 'corinthian-admin-column-manager' ) } isDestructive onClick={ onRemove } />
				</div>
			</div>
			{ expanded && (
				<div className="corinthian-row__panel" id={ panelId }>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Label', 'corinthian-admin-column-manager' ) }
						value={ column.label }
						onChange={ ( v ) => onChange( { label: v } ) }
					/>
					{ column.type !== 'native' &&
						fields.map( ( field ) => (
							<Field
								key={ field.name }
								field={ field }
								value={ column.settings[ field.name ] }
								listId="corinthian-meta-keys"
								onChange={ ( v ) => onChange( { settings: { ...column.settings, [ field.name ]: v } } ) }
							/>
						) ) }
					{ flags.length > 0 && (
						<div className="corinthian-row__flags">
							{ flags.map( ( f ) => (
								<ToggleControl
									__nextHasNoMarginBottom
									key={ f.name }
									label={ f.label }
									checked={ column.settings[ f.name ] ?? f.def }
									onChange={ ( v ) => onChange( { settings: { ...column.settings, [ f.name ]: v } } ) }
								/>
							) ) }
						</div>
					) }
				</div>
			) }
		</li>
	);
}
