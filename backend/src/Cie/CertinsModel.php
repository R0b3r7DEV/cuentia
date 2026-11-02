<?php

namespace App\Cie;

/**
 * The CERTINS E model of the Generalitat Valenciana — the certificate of a low-voltage "instalación receptora
 * específica" — as its sections, options and labels are printed in the official model (CERTINS E, 04/2009).
 * Since 2023 the certificate is no longer filed as a document: it is filled in "online" in the web form of
 * procedure 440 (GVA low-voltage FAQ, 2023-11-02, question 13). Cuentia mirrors that structure so the data can
 * be copied section by section. The web form may have changed since the 2009 model: the labels here are the
 * model's, to be checked against the live form.
 *
 * ES: El modelo CERTINS E de la Generalitat (certificado de una instalación receptora específica de BT) tal como
 * imprime sus secciones, opciones y etiquetas el modelo oficial (04/2009). Desde 2023 no se presenta como
 * documento: se rellena "online" en el formulario web del trámite 440. Cuentia replica esa estructura para
 * copiar los datos sección a sección. El formulario web puede haber cambiado respecto al modelo de 2009.
 */
final class CertinsModel
{
    public const VERSION = 'CERTINS E (04/2009) + FAQ trámites BT GVA (2023-11-02)';

    /** E · Uso de la instalación. */
    public const USES = [
        'vivienda' => 'Vivienda',
        'industria' => 'Industria (incluye las granjas)',
        'almacen' => 'Almacén',
        'alumbrado_exterior' => 'Alumbrado exterior',
        'garaje_privado' => 'Garaje (uso privado)',
        'estacionamiento_publico' => 'Estacionamiento (uso público)',
        'actividades_auxiliares' => 'Actividades auxiliares',
        'temporal' => 'Instalación de carácter temporal',
        'auxiliar_obras' => 'Actividad auxiliar de obras',
        'oficina_sin_publico' => 'Oficina sin presencia de público',
        'servicios_generales' => 'Servicios Generales',
        'suministro_complementario' => 'Suministro eléctrico complementario',
        'linea_bt' => 'Línea de baja tensión no perteneciente a empresa distribuidora',
        'espectaculos' => 'Locales de espectáculos y actividades recreativas',
        'reunion_1' => 'Locales de reunión - tipo 1',
        'reunion_2' => 'Locales de reunión - tipo 2',
        'sanitarios' => 'Locales de usos sanitarios',
        'ascensor' => 'Ascensor',
        'bombas' => 'Bombas de extracción o elevación de agua, industrial o no y grupos de bombeo',
    ];

    /** C · Riesgos específicos de la instalación. */
    public const RISKS = [
        'ninguno' => 'Ninguno',
        'polvoriento' => 'Local polvoriento',
        'corrosivo' => 'Local con ambiente corrosivo',
        'baja_temperatura' => 'Local a baja temperatura',
        'mojado' => 'Local mojado',
        'humedo' => 'Local húmedo',
        'incendio_explosion' => 'Local con riesgo de incendio o explosión',
        'lamparas_at' => 'Local con lámparas de descarga de alta tensión',
        'baterias' => 'Local con baterías o acumuladores',
        'grupo_electrogeno' => 'Grupo electrógeno auxiliar de la red de distribución',
        'caldeo' => 'Conductores aislados para caldeo excluyendo los de las viviendas',
        'otros' => 'Otros',
    ];

    /** Fuente de suministro (FAQ 26: with the distribution network, the CUPS is mandatory). */
    public const SOURCES = [
        'red' => 'Red de distribución de una compañía distribuidora',
        'generacion_aislada' => 'Instalación generadora aislada',
    ];

    /** G · Base documental: the installation was executed according to... */
    public const DESIGN_BASIS = [
        'mtd' => 'Memoria Técnica de Diseño',
        'proyecto' => 'Proyecto',
    ];

    /** C · Nueva / Modificación (the app's "ampliación" and "reforma" are modifications). */
    public static function actuation(string $installationType): string
    {
        return $installationType === 'nueva' ? 'Nueva' : 'Modificación';
    }

    /**
     * Keep only known keys, in model order, without duplicates.
     *
     * @param array<string,string> $catalog
     * @return list<string>
     */
    public static function pick(mixed $values, array $catalog): array
    {
        $values = is_array($values) ? array_map('strval', $values) : [];

        return array_values(array_filter(array_keys($catalog), static fn (string $k): bool => in_array($k, $values, true)));
    }
}
