package com.example.inventario

import com.example.inventario.data.InventarioItemDto
import com.example.inventario.data.normalizeBaseUrl
import com.example.inventario.ui.mergeInventoryPages
import org.junit.Assert.assertEquals
import org.junit.Assert.assertThrows
import org.junit.Test

class AppUtilitiesTest {
    @Test
    fun baseUrl_addsRequiredTrailingSlash() {
        assertEquals("https://inventario.test/", normalizeBaseUrl(" https://inventario.test "))
        assertEquals("http://10.0.2.2:8000/", normalizeBaseUrl("http://10.0.2.2:8000/"))
    }

    @Test
    fun baseUrl_rejectsUnsupportedSchemes() {
        assertThrows(IllegalArgumentException::class.java) {
            normalizeBaseUrl("inventario.test")
        }
    }

    @Test
    fun pagination_appendsItemsWithoutDuplicatingCodes() {
        val first = listOf(InventarioItemDto(codigo = "A"), InventarioItemDto(codigo = "B"))
        val second = listOf(InventarioItemDto(codigo = "B"), InventarioItemDto(codigo = "C"))

        assertEquals(
            listOf("A", "B", "C"),
            mergeInventoryPages(first, second, reset = false).map { it.codigo },
        )
        assertEquals(
            listOf("B", "C"),
            mergeInventoryPages(first, second, reset = true).map { it.codigo },
        )
    }
}