#!/usr/bin/env python3
"""
Regenerate the TIC Suite view files from the master `vista-analisis-municipios.json`.

All 64 municipios of Nariño are emitted in every output view — no "Top N"
filtering — so the charts show the full departmental picture.

Usage:
    python3 scripts/regen-views.py
"""
from __future__ import annotations
import json
import os
import pathlib

ROOT = pathlib.Path(__file__).resolve().parent.parent
VIEWS = ROOT / 'data' / 'views'
MASTER = VIEWS / 'vista-analisis-municipios.json'


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


def regen_centros_digitales(master: dict) -> None:
    """All 64 municipios with Centros Digitales state fields."""
    municipios = []
    for m in master['municipios']:
        cd = m.get('conectividad', {}).get('centros_digitales', {}) or {}
        total      = int(cd.get('total', 0) or 0)
        operacion  = int(cd.get('operacion', 0) or 0)
        instalacion = int(cd.get('instalacion', 0) or 0)
        planeacion = max(0, total - operacion - instalacion)
        pct        = float(cd.get('pct_operacion', 0) or 0)
        municipios.append({
            'municipio': m['municipio'],
            'en_operacion': operacion,
            'en_instalacion': instalacion,
            'en_planeacion': planeacion,
            'total': total,
            'pct_operacion': round(pct, 1),
        })

    tot = {
        'cd_asignados':   sum(x['total'] for x in municipios),
        'en_operacion':   sum(x['en_operacion'] for x in municipios),
        'en_instalacion': sum(x['en_instalacion'] for x in municipios),
        'en_planeacion':  sum(x['en_planeacion'] for x in municipios),
    }

    write_view('vista-centros-digitales-estado.json', {
        'vista': 'centros_digitales_estado',
        'titulo': 'Centros Digitales – Estado de Implementación',
        'descripcion': 'Centros Digitales asignados vs en operación vs en instalación por los 64 municipios de Nariño.',
        'tipo_grafico_sugerido': 'bar_stacked',
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': tot,
    })


def regen_talento_tech(master: dict) -> None:
    """All 64 municipios with Talento Tech inscritos + participación."""
    base = []
    total = 0
    for m in master['municipios']:
        ed = m.get('educacion_digital', {}) or {}
        inscritos = int(ed.get('talento_tech_inscritos', 0) or 0)
        base.append((m['municipio'], inscritos))
        total += inscritos

    municipios = [{
        'municipio': name,
        'inscritos': ins,
        'participacion_pct': round((ins / total * 100) if total else 0.0, 2),
    } for name, ins in base]

    write_view('vista-talento-tech-inscritos.json', {
        'vista': 'talento_tech_inscritos',
        'titulo': 'Talento Tech – Inscritos por Municipio',
        'descripcion': 'Inscritos en Talento Tech (Bootcamp TIC) en los 64 municipios de Nariño.',
        'tipo_grafico_sugerido': 'bar',
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': {
            'inscritos_narino': total,
            'meta_certificados': 5000,
            'avance_pct': round((total / 5000 * 100) if total else 0.0, 2),
        },
    })


def regen_zcp(master: dict) -> None:
    """All 64 municipios with ZCP count + PDET flag."""
    municipios = []
    total_zcp = 0
    for m in master['municipios']:
        zcp = int(m.get('conectividad', {}).get('zonas_paz', 0) or 0)
        municipios.append({
            'municipio': m['municipio'],
            'zcp_asignadas': zcp,
            'pdet': bool(m.get('pdet')),
        })
        total_zcp += zcp

    write_view('vista-zcp-municipios.json', {
        'vista': 'zcp_por_municipio',
        'titulo': 'Zonas Comunitarias para la Paz – ZCP por Municipio',
        'descripcion': 'Zonas Comunitarias para la Paz (ZCP) asignadas a los 64 municipios de Nariño. Las ZCP se asignan a municipios PDET; el campo `pdet` identifica los que aplican.',
        'tipo_grafico_sugerido': 'bar_horizontal',
        'total_municipios': len(municipios),
        'municipios': municipios,
        'totales': {
            'zcp_total': total_zcp,
            'municipios_pdet': sum(1 for x in municipios if x['pdet']),
            'municipios_no_pdet': sum(1 for x in municipios if not x['pdet']),
        },
    })


def regen_comparativo(master: dict) -> None:
    """All 64 municipios with 5 program counts for radar / multi-series comparison."""
    programas = [
        'Centros Digitales',
        'Talento Tech',
        'Juntas Internet Preregistradas',
        'Computadores para Educar',
        'Avanzatec',
    ]
    datos = []
    for m in master['municipios']:
        con = m.get('conectividad', {}) or {}
        ed  = m.get('educacion_digital', {}) or {}
        datos.append({
            'municipio': m['municipio'],
            'centros_digitales':       int(con.get('centros_digitales', {}).get('total', 0) or 0),
            'talento_tech':            int(ed.get('talento_tech_inscritos', 0) or 0),
            'juntas_internet_prereg':  int(con.get('juntas_preregistradas', 0) or 0),
            'computadores':            int(ed.get('computadores', 0) or 0),
            'avanzatec':               int(ed.get('avanzatec_certificados', 0) or 0),
        })

    write_view('vista-comparativo-municipios.json', {
        'vista': 'comparativo_programas_municipios',
        'titulo': 'Cobertura Multiproyecto por Municipio',
        'descripcion': 'Comparativo de participación de los 64 municipios de Nariño en los 5 proyectos TIC principales.',
        'tipo_grafico_sugerido': 'radar',
        'total_municipios': len(datos),
        'municipios': [d['municipio'] for d in datos],
        'programas': programas,
        'datos': datos,
    })


def main() -> None:
    master = load_master()
    print(f'Master: {len(master["municipios"])} municipios')
    print('Regenerating views:')
    regen_centros_digitales(master)
    regen_talento_tech(master)
    regen_zcp(master)
    regen_comparativo(master)
    print('Done.')


if __name__ == '__main__':
    main()
