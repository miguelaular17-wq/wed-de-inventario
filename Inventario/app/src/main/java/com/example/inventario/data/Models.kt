package com.example.inventario.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String = "Inventario Android",
)

@Serializable
data class UserDto(
    val id: Long? = null,
    val name: String = "",
    val nombre: String = "",
    val email: String = "",
    val role: String = "",
    val sede: String? = null,
    @SerialName("sede_locked") val sedeLocked: Boolean = false,
    val permissions: List<String> = emptyList(),
    val sedes: List<SedeDto> = emptyList(),
) {
    val displayName: String get() = name.ifBlank { nombre }.ifBlank { email }
}

@Serializable
data class LoginResponse(
    val token: String = "",
    @SerialName("access_token") val accessToken: String = "",
    val user: UserDto = UserDto(),
) {
    val bearerToken: String get() = token.ifBlank { accessToken }
}

@Serializable
data class SedeDto(
    val id: Long = 0,
    val nombre: String = "",
    val name: String = "",
    val codigo: String = "",
) {
    val displayName: String get() = nombre.ifBlank { name }.ifBlank { codigo }.ifBlank { "Sede $id" }
    val apiValue: String get() = codigo.ifBlank { id.toString() }
}

@Serializable
data class InventarioItemDto(
    val codigo: String = "",
    val producto: String = "",
    val nombre: String = "",
    val categoria: String = "",
    val stock: Double? = null,
    val existencia: Double? = null,
    @SerialName("existencia_local") val existenciaLocal: Double? = null,
    val stocks: Map<String, Double> = emptyMap(),
    val requisiciones: List<RequisitionDto> = emptyList(),
    val unidad: String = "",
) {
    val displayName: String get() = producto.ifBlank { nombre }.ifBlank { codigo }
    val available: Double get() = existenciaLocal ?: stock ?: existencia ?: 0.0
}

@Serializable
data class PageMetaDto(
    @SerialName("current_page") val currentPage: Int = 1,
    @SerialName("last_page") val lastPage: Int = 1,
    val total: Int = 0,
)

@Serializable
data class InventoryPageDto(
    val data: List<InventarioItemDto> = emptyList(),
    val meta: PageMetaDto = PageMetaDto(),
    val filters: InventoryFiltersDto = InventoryFiltersDto(),
    @SerialName("sedes_origen") val originSites: List<SedeDto> = emptyList(),
)

@Serializable
data class InventoryFiltersDto(
    val sede: String = "",
    val categories: List<String> = emptyList(),
)

@Serializable
data class MetricasDto(
    val disponible: Double? = null,
    val stock: Double? = null,
    val promedio: Double? = null,
    val sugerido: Double? = null,
    val mensaje: String = "",
    val demanda: Double? = null,
    val excedente: Double? = null,
    val message: String = "",
    val exceso: Double? = null,
    val safe: Boolean? = null,
) {
    val available: Double? get() = disponible ?: stock
    val displayMessage: String get() = message.ifBlank { mensaje }
}

@Serializable
data class CreateRequisitionRequest(
    val codigo: String,
    val producto: String,
    @SerialName("sede_origen") val sedeOrigen: String,
    val cantidad: Int,
    val sede: String,
)

@Serializable
data class RequisitionDto(
    val id: Long = 0,
    val codigo: String = "",
    val producto: String = "",
    val cantidad: Double = 0.0,
    val estado: String = "",
    val sede: String = "",
    @SerialName("sede_origen") val sedeOrigen: String = "",
    @SerialName("created_at") val createdAt: String = "",
)

@Serializable
data class ApiMessageDto(val message: String = "")

@Serializable
data class MeResponse(val user: UserDto = UserDto())

@Serializable
data class SitesResponse(
    val data: List<SedeDto> = emptyList(),
    val active: String? = null,
    val locked: Boolean = false,
)

@Serializable
data class RequisitionsResponse(
    val data: List<RequisitionDto> = emptyList(),
)

@Serializable
data class MetricsResponse(val data: MetricasDto = MetricasDto())

@Serializable
data class RequisitionResponse(
    val data: RequisitionDto = RequisitionDto(),
    val metrics: MetricasDto? = null,
    val message: String = "",
)

data class InventoryPage(
    val items: List<InventarioItemDto>,
    val currentPage: Int,
    val lastPage: Int,
    val total: Int,
    val categories: List<String>,
    val originSites: List<SedeDto>,
)

class SessionExpiredException : Exception("La sesión expiró")
