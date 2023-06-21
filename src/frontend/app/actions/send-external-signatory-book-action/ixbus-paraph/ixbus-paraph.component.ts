import { Component, OnInit, Input } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { FormControl } from '@angular/forms';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import { catchError, tap } from 'rxjs/operators';
import { NotificationService } from '@service/notification/notification.service';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';

@Component({
    selector: 'app-ixbus-paraph',
    templateUrl: 'ixbus-paraph.component.html',
    styleUrls: ['ixbus-paraph.component.scss'],
})
export class IxbusParaphComponent implements OnInit {

    loading: boolean = true;

    currentAccount: any = null;
    usersWorkflowList: any[] = [];
    natures: any[] = [];
    messagesModel: any[] = [];
    users: any[] = [];
    ixbusDatas: any = {
        nature: '',
        messageModel: '',
        userId: '',
        signatureMode: 'electronic'  // EDISSYUM - NCH01 Changement du mode de signature IXBUS par défaut | mettre electronic au lieu de manual
    };

    injectDatasParam = {
        resId: 0,
        editable: true
    };

    selectNature = new FormControl();
    selectWorkflow = new FormControl();
    selectUser = new FormControl();

    @Input() additionalsInfos: any;
    @Input() externalSignatoryBookDatas: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public headerService: HeaderService,
        public functions: FunctionsService,
        private localStorage: LocalStorageService,
        private notifications: NotificationService
    ) { }

    ngOnInit(): void {
        this.additionalsInfos.ixbus.natures.forEach((element: any) => {
            this.natures.push({id: element.identifiant, label: element.nom});
        });

        // EDISSYUM - NCH01 IXBUS : Selection automatique des informations si une seule valeur présente
        if (this.additionalsInfos.ixbus.natures.length === 1) {
            this.selectNature.setValue(this.additionalsInfos.ixbus.natures[0].identifiant);
            this.changeModel(this.additionalsInfos.ixbus.natures[0].identifiant);
            this.ixbusDatas.nature = this.additionalsInfos.ixbus.natures[0].identifiant;
        }
        // END EDISSYUM - NCH01

        if (this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`) !== null) {
            this.ixbusDatas.signatureMode = this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`);
        }

        this.loading = false;
    }

    changeModel(natureId: string) {
        this.http.get(`../rest/ixbus/natureDetails/${natureId}`).pipe(
            tap((data: any) => {
                if (!this.functions.empty(data.messageModels)) {
                    this.messagesModel = data.messageModels.map((message: any) => ({
                        id: message.identifiant,
                        label: message.nom
                    }));
                }
                if (!this.functions.empty(data.users)) {
                    this.users = data.users.map((user: any) => ({
                        id: user.identifiant,
                        label: `${user.prenom} ${user.nom}`
                    }));
                }
                // EDISSYUM - NCH01 IXBUS : Selection automatique des informations si une seule valeur présente
                if (data.users.length === 1) {
                    this.selectUser.setValue(data.users[0].identifiant);
                    this.ixbusDatas.userId = data.users[0].identifiant;
                }

                if (data.messageModels.length === 1) {
                    this.selectWorkflow.setValue(data.messageModels[0].identifiant);
                    this.ixbusDatas.messageModel = data.messageModels[0].identifiant;
                }
                // END EDISYSUM - NCH01

                // EDISSYUM - NCH01 Selection automatique du modèle de circuit
                if (this.additionalsInfos.ixbus.modelTitle) {
                    data.messageModels.forEach((model: any) => {
                        if (model.nom.trim() === this.additionalsInfos.ixbus.modelTitle.trim()) {
                            this.selectWorkflow.setValue(model.identifiant);
                            this.ixbusDatas.messageModel = model.identifiant;
                        }
                    });
                }
                // END EDISSYUM - NCH01
            }),
            catchError((err: any) => {
                this.notifications.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidParaph() {
        if (this.additionalsInfos.attachments.length === 0 || this.natures.length === 0 || this.messagesModel.length === 0 || this.users.length === 0 || !this.ixbusDatas.nature
            || !this.ixbusDatas.messageModel || !this.ixbusDatas.userId) {
            return false;
        } else {
            return true;
        }
    }

    getRessources() {
        return this.additionalsInfos.attachments.map((e: any) => e.res_id);
    }

    getDatas() {
        this.localStorage.save(`ixBusSignatureMode_${this.headerService.user.id}`, this.ixbusDatas.signatureMode);
        this.externalSignatoryBookDatas = {
            'ixbus': this.ixbusDatas,
            'steps': []
        };
        return this.externalSignatoryBookDatas;
    }
}
