SELECT 
    a.codigo AS [COD CENTRO],
    a.descripcion AS [PRODUCTO],
    'N/A' AS [Proveedor],
    CAST(a.existencia AS INT) AS [Centro existencia],
    COALESCE(sales15.total_qty, 0) AS [Centro promedio 15 dias (60d)],
    COALESCE(sales60.total_qty, 0) AS [Centro ventas],
    CONVERT(VARCHAR(10), a.fecha_ultima_venta, 120) AS [Centro ultima venta],
    CONVERT(VARCHAR(10), a.fecha_ultima_compra, 120) AS [Centro ultima compra]
FROM [dbo].[articulos] a WITH (NOLOCK)

-- Sales 15d
LEFT JOIN (
    SELECT vi.articulo, SUM(vi.cantidad) as total_qty
    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
    INNER JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
        ON v.tipo_documento = vi.tipo_documento 
        AND v.numero_documento = vi.numero_documento
    WHERE v.tipo_documento = 'FAC'
        AND v.fecha_emision >= DATEADD(day, -15, CAST(GETDATE() AS DATE))
    GROUP BY vi.articulo
) sales15 ON sales15.articulo = a.codigo

-- Sales 60d
LEFT JOIN (
    SELECT vi.articulo, SUM(vi.cantidad) as total_qty
    FROM [dbo].[documentos_venta] v WITH (NOLOCK)
    INNER JOIN [dbo].[documentos_venta_items] vi WITH (NOLOCK)
        ON v.tipo_documento = vi.tipo_documento 
        AND v.numero_documento = vi.numero_documento
    WHERE v.tipo_documento = 'FAC'
        AND v.fecha_emision >= DATEADD(day, -60, CAST(GETDATE() AS DATE))
    GROUP BY vi.articulo
) sales60 ON sales60.articulo = a.codigo

WHERE a.existencia > 0 
    OR sales15.total_qty > 0 
    OR sales60.total_qty > 0
ORDER BY [Centro ventas] DESC
