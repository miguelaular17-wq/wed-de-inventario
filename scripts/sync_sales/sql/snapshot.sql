SELECT
    a.codigo                                         AS codigo,
    CAST(ISNULL(ex.actual, 0) AS INT)                AS existencia,
    ISNULL(s15.total_qty, 0)                         AS ventas_15d,
    ISNULL(s60.total_qty, 0)                         AS ventas_60d,
    CONVERT(VARCHAR(19), a.fecha_ultima_venta,  120) AS ultima_venta,
    CONVERT(VARCHAR(19), a.fecha_ultima_compra, 120) AS ultima_compra,
    a.descripcion                                    AS descripcion,
    ISNULL(a.precio1_moneda2_uni1, 0)                AS precio_unidad,
    ISNULL(a.precio2_moneda2_uni1, ISNULL(a.precio1_moneda2_uni1, 0)) AS precio_mayor,
    c_padre.descripcion                              AS categoria,
    c_sub.descripcion                                AS subcategoria
FROM [dbo].[articulos] a WITH (NOLOCK)
LEFT JOIN [dbo].[existencias] ex WITH (NOLOCK) 
    ON a.id = ex.id_articulo AND ex.almacen = '01'
LEFT JOIN [dbo].[categorias] c_sub WITH (NOLOCK) ON a.categoria = c_sub.codigo
LEFT JOIN [dbo].[categorias] c_padre WITH (NOLOCK) ON c_sub.id_padre = c_padre.id
LEFT JOIN (
    SELECT ISNULL(a2.codigo, vi.articulo) AS articulo, SUM(vi.cantidad) AS total_qty
    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
        ON v.tipo_documento = vi.tipo_documento
        AND v.numero_documento = vi.numero_documento
    LEFT JOIN [dbo].[articulos_codigos] ac WITH (NOLOCK) ON vi.articulo = ac.codigo
    LEFT JOIN [dbo].[articulos] a2 WITH (NOLOCK) ON ac.articulo = a2.id
    WHERE v.tipo_documento = 'FAC'
        AND v.fecha_emision >= DATEADD(day, -15, CAST(GETDATE() AS DATE))
    GROUP BY ISNULL(a2.codigo, vi.articulo)
) s15 ON s15.articulo = a.codigo
LEFT JOIN (
    SELECT ISNULL(a2.codigo, vi.articulo) AS articulo, SUM(vi.cantidad) AS total_qty
    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
    JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
        ON v.tipo_documento = vi.tipo_documento
        AND v.numero_documento = vi.numero_documento
    LEFT JOIN [dbo].[articulos_codigos] ac WITH (NOLOCK) ON vi.articulo = ac.codigo
    LEFT JOIN [dbo].[articulos] a2 WITH (NOLOCK) ON ac.articulo = a2.id
    WHERE v.tipo_documento = 'FAC'
        AND v.fecha_emision >= DATEADD(day, -60, CAST(GETDATE() AS DATE))
    GROUP BY ISNULL(a2.codigo, vi.articulo)
) s60 ON s60.articulo = a.codigo
WHERE a.codigo IS NOT NULL AND LTRIM(RTRIM(a.codigo)) <> ''
