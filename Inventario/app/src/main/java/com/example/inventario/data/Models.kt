package com.example.inventario.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement

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

@Serializable
data class ChoiceDto(
    val value: String = "",
    val label: String = "",
)

@Serializable
data class ServiceOptionsDto(
    val sedes: List<String> = emptyList(),
    @SerialName("sede_activa") val activeSite: String? = null,
    @SerialName("sede_bloqueada") val siteLocked: Boolean = false,
    @SerialName("tipos_gestion") val managementTypes: List<ChoiceDto> = emptyList(),
    @SerialName("tipos_dispositivo") val deviceTypes: List<ChoiceDto> = emptyList(),
    @SerialName("rangos_garantia") val warrantyRanges: List<ChoiceDto> = emptyList(),
    @SerialName("prioridades") val priorities: List<ChoiceDto> = emptyList(),
    val estados: List<ChoiceDto> = emptyList(),
    val checklist: List<ChoiceDto> = emptyList(),
    val checklists: Map<String, List<ChoiceDto>> = emptyMap(),
    @SerialName("puede_transferir") val canTransfer: Boolean = false,
    val tecnicos: List<TechnicianDto> = emptyList(),
)

@Serializable
data class TechnicianDto(
    val id: Long = 0,
    val nombre: String = "",
    val sede: String = "",
    val label: String = "",
)

@Serializable
data class ServiceOptionsResponse(val data: ServiceOptionsDto = ServiceOptionsDto())

@Serializable
data class ServiceEventDto(
    val id: Long = 0,
    val tipo: String = "",
    val descripcion: String = "",
    val usuario: String? = null,
    val fecha: String? = null,
)

@Serializable
data class ServiceOrderDto(
    val id: Long = 0,
    val codigo: String = "",
    val sede: String = "",
    @SerialName("tipo_gestion") val managementType: String = "ST",
    @SerialName("tipo_gestion_label") val managementTypeLabel: String = "",
    @SerialName("tipo_dispositivo") val deviceType: String = "celular",
    @SerialName("tipo_dispositivo_label") val deviceTypeLabel: String = "",
    @SerialName("rango_garantia") val warrantyRange: String? = null,
    @SerialName("valor_dispositivo") val deviceValue: Double? = null,
    @SerialName("cliente_nombre") val clientName: String = "",
    @SerialName("cliente_telefono") val clientPhone: String? = null,
    @SerialName("cliente_cedula") val clientId: String? = null,
    val imei: String = "",
    val serial: String? = null,
    val marca: String? = null,
    val modelo: String? = null,
    val color: String? = null,
    val almacenamiento: String? = null,
    val falla: String = "",
    val accesorios: String? = null,
    val diagnostico: String? = null,
    val estado: String = "",
    @SerialName("estado_label") val statusLabel: String = "",
    @SerialName("estados_permitidos") val allowedStatuses: List<ChoiceDto> = emptyList(),
    val prioridad: String = "",
    @SerialName("prioridad_label") val priorityLabel: String = "",
    @SerialName("fecha_ingreso") val receivedDate: String? = null,
    @SerialName("fecha_prometida") val promisedDate: String? = null,
    val observaciones: String? = null,
    val inspeccion: JsonElement? = null,
    @SerialName("creado_por") val createdBy: String? = null,
    val eventos: List<ServiceEventDto> = emptyList(),
)

@Serializable
data class ServiceOrdersResponse(
    val data: List<ServiceOrderDto> = emptyList(),
    val meta: PageMetaDto = PageMetaDto(),
)

@Serializable
data class ServiceOrderResponse(
    val data: ServiceOrderDto = ServiceOrderDto(),
    val message: String = "",
)

@Serializable
data class CreateServiceOrderRequest(
    val sede: String,
    @SerialName("tipo_gestion") val managementType: String,
    @SerialName("tipo_dispositivo") val deviceType: String = "celular",
    @SerialName("enviar_otra_sede") val sendToOtherSite: Boolean = false,
    @SerialName("tecnico_destino_id") val destinationTechnicianId: Long? = null,
    @SerialName("rango_garantia") val warrantyRange: String? = null,
    @SerialName("valor_dispositivo") val deviceValue: Double? = null,
    @SerialName("cliente_nombre") val clientName: String? = null,
    @SerialName("cliente_telefono") val clientPhone: String? = null,
    @SerialName("cliente_cedula") val clientId: String? = null,
    val imei: String? = null,
    @SerialName("imei_no_aplica") val imeiNotApplicable: Boolean = false,
    val serial: String? = null,
    val marca: String,
    val modelo: String,
    val color: String,
    val almacenamiento: String,
    val falla: String,
    val accesorios: String? = null,
    val prioridad: String,
    @SerialName("fecha_prometida") val promisedDate: String? = null,
    val observaciones: String? = null,
    val inspeccion: Map<String, String> = emptyMap(),
    @SerialName("firma_recepcion_cliente") val clientSignature: String? = null,
    @SerialName("usar_equipo_existente") val useExistingDevice: Boolean = false,
)

@Serializable
data class ChangeServiceOrderStatusRequest(
    val estado: String,
    val comentario: String,
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
