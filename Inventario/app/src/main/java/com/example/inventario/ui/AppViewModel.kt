package com.example.inventario.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.example.inventario.data.CreateRequisitionRequest
import com.example.inventario.data.InventarioItemDto
import com.example.inventario.data.InventoryRepository
import com.example.inventario.data.MetricasDto
import com.example.inventario.data.RequisitionDto
import com.example.inventario.data.SedeDto
import com.example.inventario.data.SessionExpiredException
import com.example.inventario.data.UserDto
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AppUiState(
    val checkingSession: Boolean = true,
    val user: UserDto? = null,
    val loginLoading: Boolean = false,
    val loginError: String? = null,
    val sites: List<SedeDto> = emptyList(),
    val activeSite: SedeDto? = null,
    val siteLocked: Boolean = false,
    val originSites: List<SedeDto> = emptyList(),
    val categories: List<String> = emptyList(),
    val inventory: List<InventarioItemDto> = emptyList(),
    val inventoryPage: Int = 0,
    val inventoryLastPage: Int = 1,
    val inventoryLoading: Boolean = false,
    val inventoryError: String? = null,
    val search: String = "",
    val category: String = "",
    val requisitions: List<RequisitionDto> = emptyList(),
    val requisitionStatus: String = "",
    val requisitionsLoading: Boolean = false,
    val requisitionsError: String? = null,
    val metrics: MetricasDto? = null,
    val metricsLoading: Boolean = false,
    val submitting: Boolean = false,
    val message: String? = null,
)

class AppViewModel(private val repository: InventoryRepository) : ViewModel() {
    private val _state = MutableStateFlow(AppUiState())
    val state: StateFlow<AppUiState> = _state.asStateFlow()

    init {
        restoreSession()
    }

    private fun restoreSession() = viewModelScope.launch {
        try {
            if (repository.savedToken().isNullOrBlank()) {
                _state.update { it.copy(checkingSession = false) }
                return@launch
            }
            val user = repository.currentUser()
            _state.update { it.copy(user = user, checkingSession = false) }
            loadInitialData()
        } catch (_: SessionExpiredException) {
            expireSession()
        } catch (error: Exception) {
            _state.update {
                it.copy(checkingSession = false, message = error.userMessage())
            }
        }
    }

    fun login(email: String, password: String) = viewModelScope.launch {
        if (email.isBlank() || password.isBlank()) {
            _state.update { it.copy(loginError = "Ingresa correo y contraseña") }
            return@launch
        }
        _state.update { it.copy(loginLoading = true, loginError = null) }
        try {
            val user = repository.login(email, password)
            _state.update { it.copy(user = user, loginLoading = false) }
            loadInitialData()
        } catch (error: Exception) {
            _state.update {
                it.copy(loginLoading = false, loginError = error.userMessage())
            }
        }
    }

    private suspend fun loadInitialData() {
        _state.update {
            it.copy(inventoryLoading = true, requisitionsLoading = true)
        }
        try {
            val sitesResponse = repository.sites()
            val sites = sitesResponse.data
            val requestedSite = sitesResponse.active ?: _state.value.user?.sede
            val activeSite = sites.firstOrNull { it.apiValue == requestedSite } ?: sites.firstOrNull()
                ?: throw IllegalStateException("No hay sedes disponibles para esta cuenta")
            _state.update {
                it.copy(sites = sites, activeSite = activeSite, siteLocked = sitesResponse.locked)
            }
            val inventoryRequest = viewModelScope.async { repository.inventory(1, "", "", activeSite.apiValue) }
            val requisitionsRequest = viewModelScope.async { repository.requisitions("", activeSite.apiValue) }
            val page = inventoryRequest.await()
            val requisitions = requisitionsRequest.await()
            _state.update {
                it.copy(
                    inventory = page.items,
                    inventoryPage = page.currentPage,
                    inventoryLastPage = page.lastPage,
                    originSites = page.originSites,
                    categories = page.categories,
                    inventoryLoading = false,
                    requisitions = requisitions,
                    requisitionsLoading = false,
                )
            }
        } catch (error: Exception) {
            handleDataError(error)
        }
    }

    fun setActiveSite(site: SedeDto) {
        if (_state.value.siteLocked || _state.value.activeSite?.apiValue == site.apiValue) return
        _state.update {
            it.copy(activeSite = site, inventory = emptyList(), requisitions = emptyList())
        }
        loadInventory(reset = true)
        loadRequisitions()
    }

    fun setSearch(value: String) {
        _state.update { it.copy(search = value) }
    }

    fun setCategory(value: String) {
        _state.update { it.copy(category = value) }
    }

    fun searchInventory() = loadInventory(reset = true)

    fun loadMoreInventory() {
        if (_state.value.inventoryPage < _state.value.inventoryLastPage) loadInventory(reset = false)
    }

    fun retryInventory() = loadInventory(reset = true)

    private fun loadInventory(reset: Boolean) = viewModelScope.launch {
        val snapshot = _state.value
        if (snapshot.inventoryLoading) return@launch
        val site = snapshot.activeSite ?: return@launch
        val pageNumber = if (reset) 1 else snapshot.inventoryPage + 1
        _state.update { it.copy(inventoryLoading = true, inventoryError = null) }
        try {
            val page = repository.inventory(pageNumber, snapshot.search, snapshot.category, site.apiValue)
            _state.update {
                it.copy(
                    inventory = mergeInventoryPages(it.inventory, page.items, reset),
                    inventoryPage = page.currentPage,
                    inventoryLastPage = page.lastPage,
                    originSites = page.originSites,
                    categories = page.categories,
                    inventoryLoading = false,
                )
            }
        } catch (error: Exception) {
            if (error is SessionExpiredException) expireSession()
            else _state.update {
                it.copy(inventoryLoading = false, inventoryError = error.userMessage())
            }
        }
    }

    fun setRequisitionStatus(status: String) {
        _state.update { it.copy(requisitionStatus = status) }
        loadRequisitions()
    }

    fun loadRequisitions() = viewModelScope.launch {
        val site = _state.value.activeSite ?: return@launch
        _state.update { it.copy(requisitionsLoading = true, requisitionsError = null) }
        try {
            val items = repository.requisitions(_state.value.requisitionStatus, site.apiValue)
            _state.update { it.copy(requisitions = items, requisitionsLoading = false) }
        } catch (error: Exception) {
            if (error is SessionExpiredException) expireSession()
            else _state.update {
                it.copy(requisitionsLoading = false, requisitionsError = error.userMessage())
            }
        }
    }

    fun loadMetrics(item: InventarioItemDto, origin: SedeDto?, quantity: Double?) =
        viewModelScope.launch {
            if (origin == null || quantity == null || quantity <= 0) {
                _state.update { it.copy(message = "Selecciona origen e ingresa una cantidad válida") }
                return@launch
            }
            _state.update { it.copy(metricsLoading = true, metrics = null) }
            try {
                if (quantity % 1.0 != 0.0) {
                    throw IllegalArgumentException("La cantidad debe ser un número entero")
                }
                val destination = _state.value.activeSite
                    ?: throw IllegalStateException("Selecciona una sede activa")
                val metrics = repository.metrics(
                    item.codigo,
                    origin.apiValue,
                    quantity.toInt(),
                    destination.apiValue,
                )
                _state.update { it.copy(metrics = metrics, metricsLoading = false) }
            } catch (error: Exception) {
                if (error is SessionExpiredException) expireSession()
                else _state.update {
                    it.copy(metricsLoading = false, message = error.userMessage())
                }
            }
        }

    fun clearMetrics() {
        _state.update { it.copy(metrics = null, metricsLoading = false) }
    }

    fun createRequisition(
        item: InventarioItemDto,
        origin: SedeDto?,
        quantity: Double?,
        onSuccess: () -> Unit,
    ) = viewModelScope.launch {
        val destination = _state.value.activeSite
        if (origin == null || destination == null || quantity == null || quantity <= 0) {
            _state.update { it.copy(message = "Completa sede, origen y cantidad") }
            return@launch
        }
        if (quantity % 1.0 != 0.0) {
            _state.update { it.copy(message = "La cantidad debe ser un número entero") }
            return@launch
        }
        _state.update { it.copy(submitting = true) }
        try {
            repository.createRequisition(
                CreateRequisitionRequest(
                    codigo = item.codigo,
                    producto = item.displayName,
                    sedeOrigen = origin.apiValue,
                    cantidad = quantity.toInt(),
                    sede = destination.apiValue,
                ),
            )
            _state.update { it.copy(submitting = false, message = "Requisición creada") }
            onSuccess()
            loadRequisitions()
        } catch (error: Exception) {
            if (error is SessionExpiredException) expireSession()
            else _state.update { it.copy(submitting = false, message = error.userMessage()) }
        }
    }

    fun cancelRequisition(item: RequisitionDto) = viewModelScope.launch {
        val site = _state.value.activeSite ?: return@launch
        try {
            repository.cancelRequisition(item.id, site.apiValue)
            _state.update { it.copy(message = "Requisición cancelada") }
            loadRequisitions()
        } catch (error: Exception) {
            if (error is SessionExpiredException) expireSession()
            else _state.update { it.copy(message = error.userMessage()) }
        }
    }

    fun updateRequisition(
        item: RequisitionDto,
        quantity: Double?,
        onSuccess: () -> Unit,
    ) = viewModelScope.launch {
        val site = _state.value.activeSite
        if (site == null || quantity == null || quantity <= 0 || quantity % 1.0 != 0.0) {
            _state.update { it.copy(message = "Ingresa una cantidad entera mayor que cero") }
            return@launch
        }

        _state.update { it.copy(submitting = true) }
        try {
            repository.createRequisition(
                CreateRequisitionRequest(
                    codigo = item.codigo,
                    producto = item.producto,
                    sedeOrigen = item.sedeOrigen,
                    cantidad = quantity.toInt(),
                    sede = site.apiValue,
                ),
            )
            _state.update { it.copy(submitting = false, message = "Requisición actualizada") }
            onSuccess()
            loadRequisitions()
        } catch (error: Exception) {
            if (error is SessionExpiredException) expireSession()
            else _state.update { it.copy(submitting = false, message = error.userMessage()) }
        }
    }

    fun logout() = viewModelScope.launch {
        try {
            repository.logout()
        } catch (_: Exception) {
            repository.clearSession()
        } finally {
            _state.value = AppUiState(checkingSession = false)
        }
    }

    fun consumeMessage() {
        _state.update { it.copy(message = null) }
    }

    private suspend fun expireSession() {
        repository.clearSession()
        _state.value = AppUiState(
            checkingSession = false,
            message = "Tu sesión expiró. Inicia sesión nuevamente.",
        )
    }

    private suspend fun handleDataError(error: Exception) {
        if (error is SessionExpiredException) expireSession()
        else _state.update {
            it.copy(
                inventoryLoading = false,
                requisitionsLoading = false,
                inventoryError = error.userMessage(),
                requisitionsError = error.userMessage(),
            )
        }
    }

    class Factory(private val repository: InventoryRepository) : ViewModelProvider.Factory {
        @Suppress("UNCHECKED_CAST")
        override fun <T : ViewModel> create(modelClass: Class<T>): T =
            AppViewModel(repository) as T
    }
}

fun mergeInventoryPages(
    current: List<InventarioItemDto>,
    incoming: List<InventarioItemDto>,
    reset: Boolean,
): List<InventarioItemDto> =
    if (reset) incoming else (current + incoming).distinctBy { it.codigo }

private fun Throwable.userMessage(): String =
    message?.takeIf { it.isNotBlank() } ?: "No fue posible conectar con el servidor"
