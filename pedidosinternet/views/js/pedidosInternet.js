const App = {
    template: `
        <div style="width:75%">
            <el-tabs type="border-card">
                <el-tab-pane label="Operaciones">
                    <el-row justify="space-between" style="align-items: center">
                        <div>
                            <span v-if="configuration.lastSynchronization">Última sincronización: {{ configuration.lastSynchronization }}</span>
                        </div>
                    </el-row>
                    <hr>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Clientes</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingClients" @click="onSynchronizeClients">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <!--el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Pedidos</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizing" :disabled="!synchronizationResult.orderLines">Sincronizar</el-button>
                        </el-col>
                    </el-row-->
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Familias</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingFamilies" @click="onSynchronizeFamilies">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Productos</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingProducts" @click="onSynchronizeProducts">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <!--el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Promociones</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizing" :disabled="!synchronizationResult.promotions">Sincronizar</el-button>
                        </el-col>
                    </el-row-->
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Tarifas</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingRates"  @click="onSynchronizeRates">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Plantilla Web: Características y valores</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingWebTemplates"  @click="onSynchronizeWebTemplates">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Sincronizar Características con Productos</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingProductTemplateValues"  @click="onSynchronizeProductTemplateValues">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Sincronizar Categorías con Características</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingAsignFamiliesWithTemplates"  @click="onSynchronizeAsignFamiliesWithTemplates">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Sincronizar Marcas</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizingBrands"  @click="onSynchronizeBrands">Sincronizar</el-button>
                        </el-col>
                    </el-row>
                    <!--<el-row justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <el-col :span="18">Almacenes</el-col>
                        <el-col :span="4">
                            <el-button :loading="synchronizing" :disabled="!synchronizationResult.warehouses">Sincronizar</el-button>
                        </el-col>
                    </el-row>-->
                </el-tab-pane>
                <el-tab-pane label="Logs">
                    <div justify="space-between" style="align-items: center; margin-bottom: 1rem">
                        <div>
                            <el-button style="border-color: red; color:red; font-weight: 700" :loading="synchronizingDeleteLog"  @click="onDeleteLog">Borrar Log</el-button> <span style="font-style: italic; margin-left:20px">Es necesario cargar la página de nuevo para ver la tabla borrada</span>
                        </div>
                    </div>
                    <hr>
                    <strong>Últimos accesos al API</strong>
                    <el-table :data="logInformation" v-loading="loadingLogInformation" style="width: 100%" :default-sort="{ prop: 'initial', order: 'descending' }">
                        <el-table-column type="expand">
                            <template #default="props">
                                <div>
                                    <p>URL: {{props.row.url}}</p>
                                    <p>Contenido</p>
                                    <pre>{{props.row.content}}</pre>
                                    <p>Respuesta</p>
                                    <pre>{{props.row.answer}}</pre>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="initial" label="Inicio" sortable>
                            <template #default="scope">{{ dateFormatter.format(new Date(scope.row.initial)) }}</template>
                        </el-table-column>
                        <el-table-column prop="end" label="Fin" sortable>
                            <template #default="scope">{{ dateFormatter.format(new Date(scope.row.end)) }}</template>
                        </el-table-column>
                        <el-table-column prop="direction" label="Dirección"></el-table-column>
                    </el-table>
                </el-tab-pane>
                <el-tab-pane label="Enviar datos a Distrib">
                    <el-form :model="dataToDistrib" label-width="300px" v-loading="SendDataToDistrib">
                        <div>
                            <strong>Desde aquí se puede pasar la información de un pedido o cliente a Distrib pasándole la ID del mismo.</strong>
                        </div>
                        <div>
                            <strong>Para cuando un pedido/cliente no se ha registrado al fallar el conector o la API</strong>
                        </div>
                        <hr>
                        <el-form-item label="ID del pedido a enviar">
                            <el-input style="margin-bottom: 1rem" v-model="dataToDistrib.order_id" />
                            <el-form-item>
                                <el-button type="primary" @click="onSendOrderDataToDistrib">Enviar ID pedido</el-button>
                            </el-form-item>
                        </el-form-item>
                        <el-form-item label="ID del cliente a enviar">
                            <el-input style="margin-bottom: 1rem" v-model="dataToDistrib.client_id" />
                            <el-form-item>
                                <el-button type="primary" @click="onSendClientDataToDistrib">Enviar ID cliente</el-button>
                            </el-form-item>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
                <el-tab-pane label="Actualizar Stock">
                    <el-form :model="updateStock" label-width="300px" v-loading="loadingUpdateStock">
                        <div>
                            <strong>Desde aquí se puede actualizar a 99999 el stock de todos los productos de PrestaShop.</strong>
                        </div>
                        <hr>
                        <el-form-item label="Pulse el botón azul para ">
                            <el-form-item>
                                <el-button type="primary" @click="onUpdateStock">Actualizar Stock</el-button>
                            </el-form-item>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
                <el-tab-pane label="Configuración">
                    <el-form :model="configuration" label-width="300px" v-loading="loadingConfiguration">
                        <strong>Indique los parámetros de conexión al API del ERP</strong>
                        <hr>
                        <el-form-item label="Nombre de usuario">
                            <el-input v-model="configuration.username" />
                        </el-form-item>
                        <el-form-item label="Clave">
                            <el-input v-model="configuration.password" />
                        </el-form-item>
                        <el-form-item label="URL de conexión">
                            <el-input v-model="configuration.url" />
                        </el-form-item>
                        <el-form-item label="Añadido URL">
                            <el-input v-model="configuration.url_append" />
                        </el-form-item>
                        <el-form-item label="Id Cliente">
                            <el-input v-model="configuration.client_id" />
                        </el-form-item>
                        <el-form-item label="Clave secreta cliente">
                            <el-input v-model="configuration.client_secret" />
                        </el-form-item>
                        <el-form-item label="Scope">
                            <el-input v-model="configuration.scope" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="onConfigurationSave">Guardar</el-button>
                        </el-form-item>
                    </el-form>
                </el-tab-pane>
            </el-tabs>
        </div>
    `,
    data() {
        return {
            dateFormatter: new Intl.DateTimeFormat('es-ES', {
                year: 'numeric', month: 'numeric', day: 'numeric',
                hour: 'numeric', minute: 'numeric', second: 'numeric',
                hour12: false,
                timeZone: 'Europe/Madrid'
            }),

            prestashopToken: '',

            configuration: {},
            logInformation: [],
            synchronizationResult: {},
            dataToDistrib: {},
            loadingConfiguration: false,
            loadingUpdateStock: false,
            SendDataToDistrib: false,

            loadingLogInformation: false,
            synchronizing: false,
            synchronizingClients: false,
            synchronizingFamilies: false,
            synchronizingProducts: false,
            synchronizingRates: false,
            synchronizingWebTemplates: false,
            synchronizingProductTemplateValues: false,
            synchronizingAsignFamiliesWithTemplates: false,
            synchronizingBrands: false,
            DeleteLog: false,
        }
    },
    methods:{
        async initializePrestashop(){
            this.prestashopToken = document.querySelector('body').attributes['data-token'].value
        },
        async getConfiguration(){
            this.loadingConfiguration = true
            const res = await axios.get(window.pedidosInternet.configuration)
            this.loadingConfiguration = false
            if (res.status === 200) {
                this.configuration = res.data
            }
        },
        async onUpdateStock() {
            this.loadingUpdateStock = true
            const res = await axios.get(window.pedidosInternet.updateStock)
            this.loadingUpdateStock = false
        },
        async getLogInformation(){
            this.loadingLogInformation = true
            const res = await axios.get(window.pedidosInternet.logs)
            this.loadingLogInformation = false
            if (res.status === 200) {
                this.logInformation = res.data
            }
        },
        async onConfigurationSave(){
            this.loadingConfiguration = true
            const res = await axios.patch(window.pedidosInternet.configuration, this.configuration)
            this.loadingConfiguration = false
            if (res.status === 202) {
                ElementPlus.ElNotification({
                    type: 'success',
                    message: 'Cambios guardados correctamente'
                })
            } else {
                ElementPlus.ElNotification({
                    type: 'error',
                    message: 'No se han podido guardar los cambios'
                })
            }
        },
        async onSendOrderDataToDistrib(){
            this.SendDataToDistrib = true
            const res = await axios.patch(window.pedidosInternet.orderDataToDistrib, this.dataToDistrib)
            this.SendDataToDistrib = false
            if (res.status === 202) {
                ElementPlus.ElNotification({
                    type: 'success',
                    message: 'Datos enviados a Distrib correctamente'
                })
            } else {
                ElementPlus.ElNotification({
                    type: 'error',
                    message: 'No se han podido enviar los datos a Distrib'
                })
            }
        },
        async onSendClientDataToDistrib(){
            this.SendDataToDistrib = true
            const res = await axios.patch(window.pedidosInternet.clientDataToDistrib, this.dataToDistrib)
            this.SendDataToDistrib = false
            if (res.status === 202) {
                ElementPlus.ElNotification({
                    type: 'success',
                    message: 'Datos enviados a Distrib correctamente'
                })
            } else {
                ElementPlus.ElNotification({
                    type: 'error',
                    message: 'No se han podido enviar los datos a Distrib'
                })
            }
        },

        async onSynchronizeClients() {
            this.synchronizingClients = true
            const res = await axios.get(window.pedidosInternet.synchronizeClients)
            this.synchronizingClients = false
        },
        async onSynchronizeFamilies() {
            this.synchronizingFamilies = true
            const res = await axios.get(window.pedidosInternet.synchronizeFamilies)
            this.synchronizingFamilies = false
        },
        async onSynchronizeProducts() {
            this.synchronizingProducts = true
            const res = await axios.get(window.pedidosInternet.synchronizeProducts)
            this.synchronizingProducts = false
        },
        async onSynchronizeRates(){
            this.synchronizingRates = true
            const res = await axios.get(window.pedidosInternet.synchronizeRates)
            this.synchronizingRates = false
        },
        async onSynchronizeWebTemplates(){
            this.synchronizingWebTemplates = true
            const res = await axios.get(window.pedidosInternet.synchronizeWebTemplates)
            this.synchronizingWebTemplates = false
        },
        async onSynchronizeProductTemplateValues(){
            this.synchronizingProductTemplateValues = true
            const res = await axios.get(window.pedidosInternet.synchronizeProductTemplateValues)
            this.synchronizingProductTemplateValues = false
        },
        async onSynchronizeAsignFamiliesWithTemplates(){
            this.synchronizingAsignFamiliesWithTemplates = true
            const res = await axios.get(window.pedidosInternet.synchronizeAsignFamiliesWithTemplates)
            this.synchronizingAsignFamiliesWithTemplates = false
        },
        async onSynchronizeBrands(){
            this.synchronizingBrands = true
            const res = await axios.get(window.pedidosInternet.synchronizeBrands)
            this.synchronizingBrands = false
        },
        async onDeleteLog(){
            this.synchronizingDeleteLog = true
            const res = await axios.get(window.pedidosInternet.DeleteLog)
            this.synchronizingDeleteLog = false
        },

    },
    async mounted() {
        await this.initializePrestashop()
        this.getConfiguration()
        this.getLogInformation()
    }
}

const app = Vue.createApp(App);
app.use(ElementPlus, {
    locale: ElementPlusLocaleEs,
});
app.mount("#adminApp");
