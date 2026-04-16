#!/usr/bin/env python3
"""
Regenerate the Py Ondas view files from the master
`data/views/ondas/proyecto-ondas.json`.

Emits flat views — one measure per municipio or per categorical bucket —
ready to be ingested by the TIC Suite plugin data provider.

Usage:
    python3 scripts/regen-ondas.py
"""
from __future__ import annotations
import json
import os
import pathlib
from typing import Callable

ROOT = pathlib.Path(__file__).resolve().parent.parent
VIEWS = ROOT / 'data' / 'views' / 'ondas'
MASTER = VIEWS / 'proyecto-ondas.json'


def load_master() -> dict:
    with MASTER.open() as f:
        return json.load(f)


def write_view(filename: str, payload: dict) -> None:
    path = VIEWS / filename
    with path.open('w') as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)
    rows = len(payload.get('municipios', payload.get('datos', [])))
    size = os.path.getsize(path)
    print(f'  wrote {filename:45s}  rows={rows:3d}  size={size:,}B')


def _i(v) -> int:
    try:
        return int(v or 0)
    except (TypeError, ValueError):
        return 0


def _f(v) -> float:
    try:
        return float(v or 0.0)
    except (TypeError, ValueError):
        return 0.0


# ----------------------------------------------------------------------
# Municipio-level views (geographic)
# ----------------------------------------------------------------------

def regen_proyectos(master: dict) -> None:
    """Proyectos Ondas por municipio: 2024, 2025, total, variación."""
    rows = []
    t24 = t25 = tot = 0
    for m in master['municipios']:
        p = m.get('proyectos', {}) or {}
        p24, p25 = _i(p.get('2024')), _i(p.get('2025'))
        row = {
            'municipio': m['nombre'],
            'proyectos_2024': p24,
            'proyectos_2025': p25,
            'total': _i(p.get('total')) or (p24 + p25),
            'variacion_pct': _f(p.get('variacion_pct')),
        }
        rows.append(row)
        t24 += p24
        t25 += p25
        tot += row['total']

    write_view('vista-ondas-proyectos-municipio.json', {
        'vista': 'ondas_proyectos_municipio',
        'titulo': 'Ondas - Proyectos por Municipio (2024-2025)',
        'descripcion': 'Proyectos Ondas por municipio de Nariño, comparando las vigencias 2024 y 2025.',
        'tipo_grafico_sugerido': 'bar_stacked',
        'total_municipios': len(rows),
        'municipios': rows,
        'totales': {
            'proyectos_2024': t24,
            'proyectos_2025': t25,
            'total_departamento': tot,
        },
    })


def regen_profesores(master: dict) -> None:
    """Profesores Ondas por municipio: 2024, 2025, total."""
    rows = []
    t24 = t25 = tot = 0
    for m in master['municipios']:
        p = m.get('profesores', {}) or {}
        p24, p25 = _i(p.get('2024')), _i(p.get('2025'))
        rows.append({
            'municipio': m['nombre'],
            'profesores_2024': p24,
            'profesores_2025': p25,
            'total': _i(p.get('total')) or (p24 + p25),
        })
        t24 += p24
        t25 += p25
        tot += rows[-1]['total']

    write_view('vista-ondas-profesores-municipio.json', {
        'vista': 'ondas_profesores_municipio',
        'titulo': 'Ondas - Profesores por Municipio (2024-2025)',
        'descripcion': 'Profesores vinculados a Ondas por municipio de Nariño para las vigencias 2024 y 2025.',
        'tipo_grafico_sugerido': 'bar_stacked',
        'total_municipios': len(rows),
        'municipios': rows,
        'totales': {
            'profesores_2024': t24,
            'profesores_2025': t25,
            'total_departamento': tot,
        },
    })


def regen_ninos(master: dict) -> None:
    """Niños 2025 Ondas por municipio."""
    rows = []
    total = 0
    for m in master['municipios']:
        n = m.get('ninos_2025', {}) or {}
        tot = _i(n.get('total'))
        rows.append({
            'municipio': m['nombre'],
            'ninos_2025': tot,
        })
        total += tot

    write_view('vista-ondas-ninos-municipio.json', {
        'vista': 'ondas_ninos_municipio',
        'titulo': 'Ondas - Niños Inscritos por Municipio (2025)',
        'descripcion': 'Niños y niñas inscritos en Ondas por municipio de Nariño en la vigencia 2025.',
        'tipo_grafico_sugerido': 'bar',
        'total_municipios': len(rows),
        'municipios': rows,
        'totales': {
            'ninos_departamento': total,
        },
    })


def regen_instituciones(master: dict) -> None:
    """Instituciones Educativas únicas por municipio."""
    rows = []
    total = 0
    for m in master['municipios']:
        ie = m.get('instituciones_educativas', {}) or {}
        uniq = _i(ie.get('total_unicas_combinadas'))
        rows.append({
            'municipio': m['nombre'],
            'ie_2024': _i(ie.get('2024_total_unicas')),
            'ie_2025': _i(ie.get('2025_total_unicas')),
            'ie_unicas_combinadas': uniq,
        })
        total += uniq

    write_view('vista-ondas-instituciones-municipio.json', {
        'vista': 'ondas_instituciones_municipio',
        'titulo': 'Ondas - Instituciones Educativas por Municipio',
        'descripcion': 'Instituciones Educativas únicas con proyectos Ondas por municipio de Nariño.',
        'tipo_grafico_sugerido': 'bar',
        'total_municipios': len(rows),
        'municipios': rows,
        'totales': {
            'ie_unicas_departamento': total,
        },
    })


# ----------------------------------------------------------------------
# Categorical (distribution) views — based on totales_departamentales
# ----------------------------------------------------------------------

def _distribution_view(
    slug: str,
    filename: str,
    titulo: str,
    descripcion: str,
    dim_label: str,
    items: dict,
    measure_field: str = 'cantidad',
    chart_hint: str = 'donut',
) -> None:
    """Emit a simple categorical view: [{ category, measure }, …]."""
    datos = [
        {dim_label: str(k), measure_field: _i(v)}
        for k, v in items.items()
        if _i(v) > 0
    ]
    write_view(filename, {
        'vista': slug,
        'titulo': titulo,
        'descripcion': descripcion,
        'tipo_grafico_sugerido': chart_hint,
        'data': datos,
        'totales': {
            'total': sum(x[measure_field] for x in datos),
            'categorias': len(datos),
        },
        # Force canonical plugin shape for these categorical views so the
        # data provider doesn't have to infer dimensions/measures.
        'id': slug,
        'name': titulo,
        'category': 'categorical',
        'dimensions': [dim_label],
        'measures': [measure_field],
    })


def regen_ninos_genero(totales: dict) -> None:
    _distribution_view(
        'ondas_ninos_genero',
        'vista-ondas-ninos-genero.json',
        'Ondas - Niños por Género (2025)',
        'Distribución de género de los niños y niñas inscritos en Ondas 2025.',
        'genero',
        totales.get('distribucion_genero_ninos', {}),
        measure_field='ninos',
        chart_hint='donut',
    )


def regen_ninos_etnico(totales: dict) -> None:
    _distribution_view(
        'ondas_ninos_etnico',
        'vista-ondas-ninos-etnico.json',
        'Ondas - Niños por Grupo Étnico (2025)',
        'Distribución étnica de los niños y niñas inscritos en Ondas 2025.',
        'grupo_etnico',
        totales.get('distribucion_etnica_ninos', {}),
        measure_field='ninos',
        chart_hint='donut',
    )


def regen_ninos_estrato(totales: dict) -> None:
    _distribution_view(
        'ondas_ninos_estrato',
        'vista-ondas-ninos-estrato.json',
        'Ondas - Niños por Estrato (2025)',
        'Distribución por estrato socioeconómico de los niños y niñas inscritos en Ondas 2025.',
        'estrato',
        totales.get('distribucion_estrato_ninos', {}),
        measure_field='ninos',
        chart_hint='bar',
    )


def regen_ninos_grado(totales: dict) -> None:
    """Distribution by grade — sort numerically for a cleaner bar chart."""
    items = totales.get('distribucion_grado_ninos', {}) or {}
    # Sort: numeric grades asc, then "Sin dato" last
    def _sort_key(k):
        try:
            return (0, int(k))
        except (TypeError, ValueError):
            return (1, 0)
    sorted_items = dict(sorted(items.items(), key=lambda kv: _sort_key(kv[0])))
    _distribution_view(
        'ondas_ninos_grado',
        'vista-ondas-ninos-grado.json',
        'Ondas - Niños por Grado Escolar (2025)',
        'Distribución por grado escolar de los niños y niñas inscritos en Ondas 2025.',
        'grado',
        sorted_items,
        measure_field='ninos',
        chart_hint='bar',
    )


def regen_profesores_genero(totales: dict) -> None:
    _distribution_view(
        'ondas_profesores_genero',
        'vista-ondas-profesores-genero.json',
        'Ondas - Profesores por Género (2025)',
        'Distribución de género de los profesores vinculados a Ondas 2025.',
        'genero',
        totales.get('distribucion_genero_profesores', {}),
        measure_field='profesores',
        chart_hint='donut',
    )


def regen_profesores_areas(totales: dict) -> None:
    """Top 15 areas of teachers."""
    items = totales.get('distribucion_areas_profesores', {}) or {}
    # Keep only top 15 by count; the tail is very long noise.
    top = dict(sorted(items.items(), key=lambda kv: _i(kv[1]), reverse=True)[:15])
    _distribution_view(
        'ondas_profesores_areas',
        'vista-ondas-profesores-areas.json',
        'Ondas - Áreas de Profesores (Top 15, 2025)',
        'Las 15 áreas con más profesores vinculados a Ondas 2025.',
        'area',
        top,
        measure_field='profesores',
        chart_hint='bar',
    )


# ----------------------------------------------------------------------
# Main
# ----------------------------------------------------------------------

def main() -> None:
    master = load_master()
    n_muni = len(master['municipios'])
    totales = master.get('totales_departamentales', {}) or {}
    print(f'Master: {n_muni} municipios con presencia Ondas')
    print('Regenerating Py Ondas views:')
    regen_proyectos(master)
    regen_profesores(master)
    regen_ninos(master)
    regen_instituciones(master)
    regen_ninos_genero(totales)
    regen_ninos_etnico(totales)
    regen_ninos_estrato(totales)
    regen_ninos_grado(totales)
    regen_profesores_genero(totales)
    regen_profesores_areas(totales)
    print('Done.')


if __name__ == '__main__':
    main()
