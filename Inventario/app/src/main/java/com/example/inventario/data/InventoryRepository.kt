package com.example.inventario.data

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.contentOrNull
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import retrofit2.HttpException

interface InventoryRepository {
    suspend fun savedToken(): String?
    suspend fun login(email: String, password: String): UserDto
    suspend fun currentUser(): UserDto
    suspend fun logout()
    suspend fun serviceOptions(): ServiceOptionsDto
    suspend fun serviceOrders(page: Int, query: String, status: String, site: String): ServiceOrdersResponse
    suspend fun serviceOrder(id: Long): ServiceOrderDto
    suspend fun createServiceOrder(request: CreateServiceOrderRequest): ServiceOrderDto
    suspend fun changeServiceOrderStatus(id: Long, status: String, comment: String): ServiceOrderDto
    suspend fun sites(): SitesResponse
    suspend fun inventory(page: Int, query: String, category: String, site: String): InventoryPage
    suspend fun requisitions(status: String, site: String): List<RequisitionDto>
    suspend fun metrics(code: String, origin: String, quantity: Int, site: String): MetricasDto
    suspend fun createRequisition(request: CreateRequisitionRequest): RequisitionDto
    suspend fun cancelRequisition(id: Long, site: String)
    suspend fun clearSession()
}

class NetworkInventoryRepository(
    private val api: InventarioApi,
    private val sessionStore: SessionStore,
) : InventoryRepository {
    override suspend fun savedToken(): String? = sessionStore.token()

    override suspend fun login(email: String, password: String): UserDto =
        try {
            apiCall {
                val response = api.login(LoginRequest(email.trim(), password))
                require(response.bearerToken.isNotBlank()) { "El servidor no devolvió un token" }
                sessionStore.saveToken(response.bearerToken)
                response.user
            }
        } catch (_: SessionExpiredException) {
            throw IllegalArgumentException("Correo o contraseña incorrectos")
        }

    override suspend fun currentUser(): UserDto = apiCall { api.me().user }

    override suspend fun serviceOptions(): ServiceOptionsDto =
        apiCall { api.serviceOptions().data }

    override suspend fun serviceOrders(
        page: Int,
        query: String,
        status: String,
        site: String,
    ): ServiceOrdersResponse = apiCall {
        api.serviceOrders(
            page = page,
            query = query.ifBlank { null },
            status = status.ifBlank { null },
            site = site.ifBlank { null },
        )
    }

    override suspend fun serviceOrder(id: Long): ServiceOrderDto =
        apiCall { api.serviceOrder(id).data }

    override suspend fun createServiceOrder(request: CreateServiceOrderRequest): ServiceOrderDto =
        apiCall { api.createServiceOrder(request).data }

    override suspend fun changeServiceOrderStatus(id: Long, status: String, comment: String): ServiceOrderDto =
        apiCall {
            api.changeServiceOrderStatus(id, ChangeServiceOrderStatusRequest(status, comment)).data
        }

    override suspend fun logout() {
        try {
            apiCall {
                val response = api.logout()
                if (!response.isSuccessful) throw HttpException(response)
            }
        } finally {
            sessionStore.clear()
        }
    }

    override suspend fun sites(): SitesResponse = apiCall { api.sedes() }

    override suspend fun inventory(
        page: Int,
        query: String,
        category: String,
        site: String,
    ): InventoryPage = apiCall {
        val response = api.inventory(page, query.ifBlank { null }, category.ifBlank { null }, site)
        InventoryPage(
            items = response.data,
            currentPage = response.meta.currentPage,
            lastPage = response.meta.lastPage,
            total = response.meta.total,
            categories = response.filters.categories,
            originSites = response.originSites,
        )
    }

    override suspend fun requisitions(status: String, site: String): List<RequisitionDto> =
        apiCall { api.requisitions(status.ifBlank { null }, site).data }

    override suspend fun metrics(
        code: String,
        origin: String,
        quantity: Int,
        site: String,
    ): MetricasDto = apiCall { api.metrics(code, origin, quantity, site).data }

    override suspend fun createRequisition(request: CreateRequisitionRequest): RequisitionDto =
        apiCall { api.createRequisition(request).data }

    override suspend fun cancelRequisition(id: Long, site: String) {
        apiCall {
            val response = api.cancelRequisition(id, site)
            if (!response.isSuccessful) throw HttpException(response)
        }
    }

    override suspend fun clearSession() = sessionStore.clear()

    private suspend fun <T> apiCall(block: suspend () -> T): T = try {
        block()
    } catch (error: HttpException) {
        if (error.code() == 401) throw SessionExpiredException()
        val body = error.response()?.errorBody()?.string()
        val message = runCatching {
            body?.let {
                Json.parseToJsonElement(it).jsonObject["message"]?.jsonPrimitive?.contentOrNull
            }
        }.getOrNull()
        throw IllegalStateException(message?.takeIf { it.isNotBlank() } ?: "Error HTTP ${error.code()}")
    }
}
