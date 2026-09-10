package com.example.inventario

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.onAllNodesWithText
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import com.example.inventario.data.CreateRequisitionRequest
import com.example.inventario.data.InventarioItemDto
import com.example.inventario.data.InventoryPage
import com.example.inventario.data.InventoryRepository
import com.example.inventario.data.MetricasDto
import com.example.inventario.data.RequisitionDto
import com.example.inventario.data.SedeDto
import com.example.inventario.data.SitesResponse
import com.example.inventario.data.UserDto
import com.example.inventario.ui.AppViewModel
import com.example.inventario.ui.InventarioApp
import com.example.inventario.ui.theme.InventarioTheme
import org.junit.Rule
import org.junit.Test

class InventarioAppTest {
    @get:Rule
    val composeRule = createComposeRule()

    @Test
    fun login_showsRequiredFields() {
        setApp(FakeRepository(authenticated = false))

        composeRule.onNodeWithText("Correo").assertIsDisplayed()
        composeRule.onNodeWithText("Contraseña").assertIsDisplayed()
        composeRule.onNodeWithText("Iniciar sesión").assertIsDisplayed()
    }

    @Test
    fun inventory_showsProductsAfterRestoringSession() {
        setApp(FakeRepository(authenticated = true))

        composeRule.waitUntil(5_000) {
            composeRule.onAllNodesWithText("Audífonos API").fetchSemanticsNodes().isNotEmpty()
        }
        composeRule.onNodeWithText("Audífonos API").assertIsDisplayed()
    }

    @Test
    fun product_opensRequisitionForm() {
        setApp(FakeRepository(authenticated = true))
        composeRule.waitUntil(5_000) {
            composeRule.onAllNodesWithText("Solicitar").fetchSemanticsNodes().isNotEmpty()
        }

        composeRule.onNodeWithText("Solicitar").performClick()
        composeRule.onNodeWithText("Nueva requisición").assertIsDisplayed()
        composeRule.onNodeWithText("Cantidad").assertIsDisplayed()
        composeRule.onNodeWithText("Consultar métricas").assertIsDisplayed()
    }

    private fun setApp(repository: InventoryRepository) {
        composeRule.setContent {
            InventarioTheme {
                InventarioApp(AppViewModel(repository))
            }
        }
    }
}

private class FakeRepository(private val authenticated: Boolean) : InventoryRepository {
    private val destination = SedeDto(codigo = "ZAMORA", nombre = "Zamora")
    private val origin = SedeDto(codigo = "JRZ", nombre = "JRZ")

    override suspend fun savedToken(): String? = if (authenticated) "token" else null
    override suspend fun login(email: String, password: String) = UserDto(email = email)
    override suspend fun currentUser() = UserDto(email = "app@test.local", sede = "ZAMORA")
    override suspend fun logout() = Unit
    override suspend fun sites() = SitesResponse(data = listOf(destination), active = "ZAMORA")
    override suspend fun inventory(page: Int, query: String, category: String, site: String) =
        InventoryPage(
            items = listOf(
                InventarioItemDto(
                    codigo = "AUD-1",
                    producto = "Audífonos API",
                    categoria = "Audio",
                    existenciaLocal = 2.0,
                ),
            ),
            currentPage = 1,
            lastPage = 1,
            total = 1,
            categories = listOf("Audio"),
            originSites = listOf(origin),
        )

    override suspend fun requisitions(status: String, site: String) = emptyList<RequisitionDto>()
    override suspend fun metrics(code: String, origin: String, quantity: Int, site: String) =
        MetricasDto(stock = 10.0, demanda = 2.0, excedente = 8.0)
    override suspend fun createRequisition(request: CreateRequisitionRequest) = RequisitionDto(id = 1)
    override suspend fun cancelRequisition(id: Long, site: String) = Unit
    override suspend fun clearSession() = Unit
}
