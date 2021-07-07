<?php declare(strict_types = 1);

/*
 * $baseURIApi
 */

?>
<div id="inner-content">
    <div id="loader-bar" class="progress">
        <div class="progress-bar progress-bar-striped active" role="progressbar"
        aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width:100%">
        </div>
    </div>
    <div class="wrapper">
        <div class="col-sm-12" v-for="s in soldesCongeNatif"> 
            <div class="col-sm-2">
                <h4 class="progress-label">{{ s.libelle }}</h4>
            </div>
            <div class="col-sm-10">
                <div class="progress">
                    <div role="progressbar" class="progress-bar progress-bar-success" :style="getWidthRestant(s)">Restants : {{ getRestant(s) }}</div>
                    <div role="progressbar" class="progress-bar progress-bar" :style="getWidthReliquat(s)">Reliquats : {{ getReliquat(s) }}</div>
                    <div role="progressbar" class="progress-bar progress-bar" :style="getWidthConsomme(s)">Consommés : {{ getConsomme(s) }}</div>
                </div>
            </div>
        </div>
        <hr>
        <a href="#plus" data-toggle="collapse">Plus...</a>
        <div id="plus" class="collapse">
            <div class="col-sm-12" v-for="s in soldesConge"> 
                <div class="col-sm-2">
                    <h4 class="progress-label">{{ s.libelle }}</h4>
                </div>
                <div class="col-sm-10">
                    <div class="progress">
                        <div role="progressbar" class="progress-bar progress-bar-success" :style="getWidthRestant(s)">Restants : {{ getRestant(s) }}</div>
                        <div role="progressbar" class="progress-bar progress-bar" :style="getWidthReliquat(s)">Reliquats : {{ getReliquat(s) }}</div>
                        <div role="progressbar" class="progress-bar progress-bar" :style="getWidthConsomme(s)">Consommés : {{ getConsomme(s) }}</div>
                    </div>
                </div>
            </div>
           <div class="col-sm-4" v-for="s in soldesAutre">
                <dl class="row">
                    <dt class="col-sm-5">{{ s.libelle }}</dt>
                    <dd class="col-sm-5">{{ getRestant(s) }} Jours</dd>
                </dl>
            </div>
            <div class="col-sm-4" v-if="soldeHeure != 0">
                <dl class="row">
                    <dt class="col-sm-5">Heure</dt>
                    <dd class="col-sm-5">{{ getSoldeHeure(soldeHeure) }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
<script>

var vm = new Vue({
    el: '#inner-content',
    data: {
        types : {},
        soldesCongeNatif : {},
        soldesConge : {},
        soldesAutre : {},
        soldeHeure : 0,
        axios : instance
    },
    computed: {
    },
    'methods' : {
        getRestant : function (solde) {
            return solde['solde'];
        },
        getWidthRestant : function (solde) {
            return "width:" + ((solde['solde'] - solde['reliquat']) / solde['solde_annuel'] * 100) + "%";
        },
        getReliquat : function (solde) {
            return solde['reliquat'];
        },
        getWidthReliquat : function (solde) {
            return "width:" + (solde['reliquat'] / solde['solde_annuel'] * 100) + "%";
        },
        getConsomme : function (solde) {
            return (solde['solde_annuel'] - solde['solde']);
        },
        getWidthConsomme : function (solde) {
            return "width:" + ((solde['solde_annuel'] - solde['solde']) / solde['solde_annuel'] * 100) + "%; background-color:gray !important;";
        },
        getSoldeHeure : function (solde) {
            var heures   = Math.floor(solde / 3600);
            var minutes = Math.floor((solde - (heures * 3600)) / 60);
            if (heures   < 10) {heures   = "0"+heures;}
            if (minutes < 10) {minutes = "0"+minutes;}
            return heures + " heure(s) et " + minutes + " minute(s)";
        }
    },
    created () {
        var vm = this;
        this.axios.get('/employe/me')
        .then((response) => {
            if (typeof response.data != 'object') {
                return;
            }
            const employe = response.data.data;
            
            vm.soldeHeure = employe.heure_solde;
        })
        .catch((error) => {
            console.log(error.response);
            console.error(error);
        }),
        this.axios.get('/absence/type')
        .then((response) => {
            if (typeof response.data != 'object') {
                return;
            }
            const types = response.data.data;
            vm.types = types;
        })
        .catch((error) => {
            console.log(error.response);
            console.error(error);
        }),
        this.axios.get('/employe/me/solde')
        .then((response) => {
            if (typeof response.data != 'object') {
                return;
            }
            const soldes = response.data.data;
            var soldesLibelleConges = new Array();
            var soldesLibelleNatif = new Array();
            var soldesLibelleAutre = new Array();
            for (var i = 0; i < soldes.length; ++i) {
                var solde = soldes[i];
                for (var j = 0; j < vm.types.length; ++j) {
                    var type = vm.types[j];
                    if (solde.type_absence === type.id) {
                        solde.libelle = type.libelle;
                        if (type.typeNatif === true && type.type === 'conges') {
                            soldesLibelleNatif.push(solde);
                        } else if (type.type === 'conges') {
                            soldesLibelleConges.push(solde);
                        } else {
                            soldesLibelleAutre.push(solde);
                        }
                    }
                }
            }
        document.getElementById('loader-bar').classList.add('hidden');
        vm.soldesCongeNatif = soldesLibelleNatif;
        vm.soldesConge = soldesLibelleConges;
        vm.soldesAutre = soldesLibelleAutre;
        })
        .catch((error) => {
            console.log(error.response);
            console.error(error);
        });
    },
    updated () {
    }
});
</script>
