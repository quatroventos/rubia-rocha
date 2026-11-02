import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { locationLabel, type DashboardStats } from '../screens/analytics';

export function LocationsMap({ locations }: { locations: DashboardStats['locations'] }) {
  const elementRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const element = elementRef.current;

    if (!element) {
      return;
    }

    const map = L.map(element, {
      scrollWheelZoom: false,
      attributionControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap',
      maxZoom: 18,
    }).addTo(map);

    const points = locations.filter((row) => row.lat != null && row.lng != null);
    const group = L.featureGroup();

    if (points.length === 0) {
      map.setView([-14.235, -51.9253], 4);
    } else {
      const maxViews = Math.max(...points.map((row) => row.views), 1);

      points.forEach((row) => {
        L.circleMarker([row.lat as number, row.lng as number], {
          radius: 8 + (row.views / maxViews) * 16,
          color: '#ed725d',
          weight: 1.5,
          fillColor: '#ed725d',
          fillOpacity: 0.38,
        })
          .bindTooltip(`${locationLabel(row)} · ${row.views}`)
          .addTo(group);
      });

      group.addTo(map);
      map.fitBounds(group.getBounds().pad(0.35), { maxZoom: 6, animate: false });
    }

    const timer = window.setTimeout(() => {
      map.invalidateSize();
    }, 80);

    return () => {
      window.clearTimeout(timer);
      map.remove();
    };
  }, [locations]);

  return <div className="geo-map" ref={elementRef} />;
}
