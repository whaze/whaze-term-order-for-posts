import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
	useSortable,
	arrayMove,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

/**
 * A single draggable term row.
 *
 * @param {Object} props
 * @param {number} props.termId The term ID used as dnd-kit item identifier.
 * @param {string} props.name   The term display name.
 */
function SortableTermItem( { termId, name } ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: termId } );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.5 : 1,
		display: 'flex',
		alignItems: 'center',
		gap: '8px',
		padding: '4px 0',
		cursor: 'grab',
		userSelect: 'none',
	};

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			{ ...attributes }
			{ ...listeners }
		>
			<span
				aria-label={ __(
					'Drag to reorder',
					'whaze-term-order-for-posts'
				) }
				style={ { color: '#757575', lineHeight: 1, flexShrink: 0 } }
			>
				⠿
			</span>
			{ name }
		</div>
	);
}

/**
 * Resolve the display order from stored order and currently assigned terms.
 *
 * Terms present in `order` come first (in stored order), then any remaining
 * assigned terms appended in their natural sequence.
 *
 * @param {number[]} assignedTermIds All term IDs currently assigned to the post.
 * @param {number[]} order           Stored custom order (may be partial or empty).
 *
 * @return {number[]} Resolved ordered list of term IDs.
 */
function resolveOrder( assignedTermIds, order ) {
	const assigned = new Set( assignedTermIds );
	const ordered = order.filter( ( id ) => assigned.has( id ) );
	const rest = assignedTermIds.filter( ( id ) => ! ordered.includes( id ) );
	return [ ...ordered, ...rest ];
}

/**
 * Renders a drag-and-drop sortable list of terms.
 *
 * @param {Object}   props
 * @param {string}   props.taxonomy        Taxonomy slug (e.g. 'category', 'genre') — used as
 *                                         the entity name in @wordpress/core-data.
 * @param {number[]} props.assignedTermIds Term IDs currently assigned to the post.
 * @param {number[]} props.order           Stored custom order.
 * @param {Function} props.onOrderChange   Callback invoked with new ordered ID array.
 */
export default function SortableTermList( {
	taxonomy,
	assignedTermIds,
	order,
	onOrderChange,
} ) {
	const [ items, setItems ] = useState( () =>
		resolveOrder( assignedTermIds, order )
	);

	// Re-sync when assigned terms or stored order change.
	useEffect( () => {
		setItems( resolveOrder( assignedTermIds, order ) );
	}, [ JSON.stringify( assignedTermIds ), JSON.stringify( order ) ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Fetch each term individually by ID.
	// core-data registers taxonomy entities under the taxonomy SLUG (not the REST base),
	// so we use `taxonomy` (e.g. 'category') — not `restBase` (e.g. 'categories').
	const termNames = useSelect(
		( select ) => {
			if ( ! assignedTermIds.length ) {
				return {};
			}

			const store = select( coreStore );
			const names = {};

			for ( const id of assignedTermIds ) {
				const term = store.getEntityRecord( 'taxonomy', taxonomy, id );
				if ( term ) {
					names[ id ] = term.name;
				}
			}

			return names;
		},
		[ taxonomy, JSON.stringify( assignedTermIds ) ] // eslint-disable-line react-hooks/exhaustive-deps
	);

	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const handleDragEnd = useCallback(
		( event ) => {
			const { active, over } = event;

			if ( ! over || active.id === over.id ) {
				return;
			}

			setItems( ( prev ) => {
				const oldIndex = prev.indexOf( active.id );
				const newIndex = prev.indexOf( over.id );
				const next = arrayMove( prev, oldIndex, newIndex );
				onOrderChange( next );
				return next;
			} );
		},
		[ onOrderChange ]
	);

	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			onDragEnd={ handleDragEnd }
		>
			<SortableContext
				items={ items }
				strategy={ verticalListSortingStrategy }
			>
				{ items.map( ( termId ) => (
					<SortableTermItem
						key={ termId }
						termId={ termId }
						name={ termNames[ termId ] ?? '…' }
					/>
				) ) }
			</SortableContext>
		</DndContext>
	);
}
