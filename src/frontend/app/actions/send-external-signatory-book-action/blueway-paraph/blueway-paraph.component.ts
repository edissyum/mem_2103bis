import { Component, OnInit, Input } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { FormControl } from '@angular/forms';
import { LocalStorageService } from '@service/local-storage.service';
import { HeaderService } from '@service/header.service';
import {NotificationService} from "@service/notification/notification.service";

@Component({
    selector: 'app-blueway-paraph',
    templateUrl: 'blueway-paraph.component.html',
    styleUrls: ['blueway-paraph.component.scss'],
})
export class BluewayParaphComponent implements OnInit {

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
        signatureMode: 'electronic'
    };

    injectDatasParam = {
        resId: 0,
        editable: true
    };

    selectNature = new FormControl();
    selectWorkflow = new FormControl();
    selectUser = new FormControl();

    // eslint-disable-next-line @typescript-eslint/member-ordering
    @Input() additionalsInfos: any;
    // eslint-disable-next-line @typescript-eslint/member-ordering
    @Input() externalSignatoryBookDatas: any;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public headerService: HeaderService,
        private localStorage: LocalStorageService
    ) { }

    ngOnInit(): void {
        let defaultNatureExist = false;
        let defaultNatureId = '';

        if (this.additionalsInfos.blueway.error) {
            console.log(this.additionalsInfos.blueway.error);
           this.notify.error('Blueway error : ' + this.additionalsInfos.blueway.error);
           return;
        }
        if (this.additionalsInfos.blueway.natures.length === 0) {
            return;
        }
        this.additionalsInfos.blueway.natures.forEach((element: any) => {
            if (element.nom === this.additionalsInfos.blueway.defaultNature) {
                defaultNatureExist = true;
                defaultNatureId = element.identifiant;
            }
            this.natures.push({id: element.identifiant, label: element.nom});
        });

        if (this.additionalsInfos.blueway.defaultNature) {
            this.ixbusDatas.nature = this.additionalsInfos.blueway.defaultNature;
            this.selectNature.setValue(this.additionalsInfos.blueway.defaultNature);
            this.changeModel(this.additionalsInfos.blueway.defaultNature);
        }

        if (this.additionalsInfos.blueway.defaultMessagesModel) {
            this.ixbusDatas.messageModel = this.additionalsInfos.blueway.defaultMessagesModel;
            this.selectWorkflow.setValue(this.additionalsInfos.blueway.defaultMessagesModel);
        } else {
            this.ixbusDatas.messageModel = this.messagesModel[0]['id'];
            this.selectWorkflow.setValue(this.messagesModel[0]['id']);
        }

        if (this.additionalsInfos.blueway.defaultUser) {
            this.ixbusDatas.userId = this.additionalsInfos.blueway.defaultUser;
            this.selectUser.setValue(this.additionalsInfos.blueway.defaultUser);
        } else {
            this.ixbusDatas.userId = this.users[0]['id'];
            this.selectUser.setValue(this.users[0]['id']);
        }

        if (this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`) !== null) {
            this.ixbusDatas.signatureMode = this.localStorage.get(`ixBusSignatureMode_${this.headerService.user.id}`);
        } else {
            this.ixbusDatas.signatureMode = 'electronic';
        }

        /* if (this.additionalsInfos.blueway.defaultNature && this.additionalsInfos.blueway.defaultMessagesModel && this.additionalsInfos.blueway.defaultUser) {
            this.isValidParaph();
        }*/

        this.loading = false;
    }

    changeModel(natureId: string) {
        this.messagesModel = [];
        this.additionalsInfos.blueway.messagesModel[natureId].forEach((element: any) => {
            this.messagesModel.push({id: element.identifiant, label: element.nom});
        });

        this.users = [];
        this.additionalsInfos.blueway.users[natureId].forEach((element: any) => {
            this.users.push({id: element.identifiant, label: element.prenom + ' ' + element.nom});
        });
    }

    isValidParaph() {
        return !(this.additionalsInfos.attachments.length === 0 || this.natures.length === 0 || this.messagesModel.length === 0 || this.users.length === 0 || !this.ixbusDatas.nature
            || !this.ixbusDatas.messageModel || !this.ixbusDatas.userId);
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
