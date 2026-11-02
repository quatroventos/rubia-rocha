import { type PointerEvent as ReactPointerEvent, type ReactNode, useRef, useState } from 'react';

export type DropPosition = { index: number; nest?: boolean };

type SortableListProps<T extends { id: string | number }> = {
  items: T[];
  onReorder: (from: number, to: number, nest?: boolean) => void;
  allowNest?: boolean;
  nestIndentPx?: number;
  depthOf?: (item: T) => number;
  renderItem: (item: T, index: number, dragHandleProps: { onPointerDown: (event: ReactPointerEvent) => void }) => ReactNode;
};

export function SortableList<T extends { id: string | number }>({
  items,
  onReorder,
  allowNest = false,
  nestIndentPx = 28,
  depthOf,
  renderItem,
}: SortableListProps<T>) {
  const [dragging, setDragging] = useState<number | null>(null);
  const [over, setOver] = useState<DropPosition | null>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const dragRef = useRef<number | null>(null);
  const overRef = useRef<DropPosition | null>(null);

  function onPointerDown(index: number, event: ReactPointerEvent) {
    if (event.button !== 0) {
      return;
    }
    event.preventDefault();
    dragRef.current = index;
    overRef.current = { index };
    setDragging(index);
    setOver({ index });

    // Nest is decided by how far the row was dragged horizontally relative to its
    // starting depth, so a child stays a child unless pulled left and a root only
    // nests when pushed right. This mirrors the WordPress menu editor behaviour.
    const startX = event.clientX;
    const startDepthPx = (depthOf ? depthOf(items[index]) : 0) * nestIndentPx;

    const wantsNest = (clientX: number): boolean => {
      if (!allowNest) {
        return false;
      }
      return startDepthPx + (clientX - startX) > nestIndentPx / 2;
    };

    const hasRowAbove = (index: number): boolean => {
      for (let i = index - 1; i >= 0; i -= 1) {
        if (i !== dragRef.current) {
          return true;
        }
      }
      return false;
    };

    const onMove = (moveEvent: PointerEvent) => {
      const root = listRef.current;
      if (!root) {
        return;
      }
      const rows = Array.from(root.querySelectorAll<HTMLElement>('[data-sort-row]'));
      let next: DropPosition = { index: items.length };
      for (let i = 0; i < rows.length; i += 1) {
        const rect = rows[i].getBoundingClientRect();
        const mid = rect.top + rect.height / 2;
        if (moveEvent.clientY < mid) {
          next = { index: i, nest: wantsNest(moveEvent.clientX) && hasRowAbove(i) };
          break;
        }
      }
      if (next.index === items.length) {
        next = { index: items.length, nest: wantsNest(moveEvent.clientX) && hasRowAbove(items.length) };
      }
      overRef.current = next;
      setOver(next);
    };

    const onUp = () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      const from = dragRef.current;
      const drop = overRef.current;
      dragRef.current = null;
      overRef.current = null;
      setDragging(null);
      setOver(null);

      if (from === null || !drop) {
        return;
      }

      if (drop.nest) {
        onReorder(from, drop.index, true);
        return;
      }

      let to = drop.index;
      if (to > from) {
        to -= 1;
      }
      to = Math.max(0, Math.min(to, items.length - 1));
      if (to !== from) {
        onReorder(from, to, false);
      }
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
  }

  const isOverEnd = over !== null && dragging !== null && over.index >= items.length;

  return (
    <div className={`sortable-list${dragging !== null ? ' is-sorting' : ''}`} ref={listRef}>
      {items.map((item, index) => {
        const isDragging = dragging === index;
        const showLineBefore = over !== null && dragging !== null && over.index === index && !over.nest;
        const showNest = over !== null && dragging !== null && over.index === index && Boolean(over.nest);
        const shift =
          over !== null && dragging !== null && !over.nest && over.index <= index && dragging > index
            ? 1
            : over !== null && dragging !== null && !over.nest && over.index > index && dragging < index
              ? -1
              : 0;

        return (
          <div key={item.id} data-sort-row className="sortable-row-wrap">
            {showLineBefore ? <div className="sortable-drop-line" /> : null}
            {showNest ? <div className="sortable-drop-line is-nest" /> : null}
            <div
              className={`sortable-row${isDragging ? ' is-dragging' : ''}`}
              style={{ transform: shift !== 0 ? `translateY(${shift * 10}px)` : undefined }}
            >
              {renderItem(item, index, {
                onPointerDown: (event) => onPointerDown(index, event),
              })}
            </div>
          </div>
        );
      })}
      {isOverEnd ? (
        <div className={`sortable-drop-line is-end${over?.nest ? ' is-nest' : ''}`} />
      ) : null}
    </div>
  );
}
