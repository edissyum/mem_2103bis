import { Component, OnInit, Inject, ViewChild, ChangeDetectorRef } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { HttpClient } from '@angular/common/http';
import { NoteEditorComponent } from '../../notes/note-editor.component';
import { XParaphComponent } from './x-paraph/x-paraph.component';
import { MaarchParaphComponent } from './maarch-paraph/maarch-paraph.component';
import { FastParaphComponent } from './fast-paraph/fast-paraph.component';
import { IParaphComponent } from './i-paraph/i-paraph.component';
import { IxbusParaphComponent } from './ixbus-paraph/ixbus-paraph.component';
import { tap, finalize, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { Router } from '@angular/router';
import { SessionStorageService } from '@service/session-storage.service';
import { BluewayParaphComponent } from './blueway-paraph/blueway-paraph.component'; // EDISSYUM - PYB01 Ajout du connecteur Blueway
import { PastellParaphComponent } from './pastell-paraph/pastell-paraph.component'; // EDISSYUM - PYB01 Ajout du connecteur Pastell

@Component({
    templateUrl: 'send-external-signatory-book-action.component.html',
    styleUrls: ['send-external-signatory-book-action.component.scss'],
})
export class SendExternalSignatoryBookActionComponent implements OnInit {

    @ViewChild('noteEditor', { static: true }) noteEditor: NoteEditorComponent;

    @ViewChild('xParaph', { static: false }) xParaph: XParaphComponent;
    @ViewChild('maarchParapheur', { static: false }) maarchParapheur: MaarchParaphComponent;
    @ViewChild('fastParapheur', { static: false }) fastParapheur: FastParaphComponent;
    @ViewChild('iParapheur', { static: false }) iParapheur: IParaphComponent;
    @ViewChild('ixbus', { static: false }) ixbus: IxbusParaphComponent;
    @ViewChild('pastell', { static: false }) pastell: PastellParaphComponent; // EDISSYUM - PYB01 Ajout du connecteur Pastell
    @ViewChild('blueway', { static: false }) blueway: BluewayParaphComponent; // EDISSYUM - PYB01 Ajout du connecteur Blueway

    loading: boolean = false;

    additionalsInfos: any = {
        destinationId: '',
        users: [],
        attachments: [],
        noAttachment: []
    };
    resourcesToSign: any[] = [];
    resourcesMailing: any[] = [];
    signatoryBookEnabled: string = '';

    externalSignatoryBookDatas: any = {
        steps: [],
        objectSent: 'attachment'
    };

    integrationsInfo: any = {
        inSignatureBook: {
            icon: 'fas fa-file-signature'
        }
    };

    errors: any;

    mainDocumentSigned: boolean = false;

    canGoToNextRes: boolean = false;
    showToggle: boolean = false;
    inLocalStorage: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialogRef: MatDialogRef<SendExternalSignatoryBookActionComponent>,
        @Inject(MAT_DIALOG_DATA) public data: any,
        private changeDetectorRef: ChangeDetectorRef,
        private notify: NotificationService,
        private functionsService: FunctionsService,
        private router: Router,
        private sessionStorage: SessionStorageService
    ) { }

    ngOnInit(): void {
        this.loading = true;
        this.showToggle = this.data.additionalInfo.showToggle;
        this.canGoToNextRes = this.data.additionalInfo.canGoToNextRes;
        this.inLocalStorage = this.data.additionalInfo.inLocalStorage;
        if (this.data.resource.integrations['inSignatureBook']) {
            this.http.get(`../rest/resources/${this.data.resource.resId}/versionsInformations`).pipe(
                tap((data: any) => {
                    this.mainDocumentSigned = data.SIGN.length !== 0;
                    if (!this.mainDocumentSigned) {
                        this.toggleDocToSign(true, this.data.resource, true);
                    }
                }),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    return of(false);
                })
            ).subscribe();
        }
        this.checkExternalSignatureBook();
    }

    onSubmit() {
        this.loading = true;
        if (this.data.resIds.length > 0) {
            this.sessionStorage.checkSessionStorage(this.inLocalStorage, this.canGoToNextRes, this.data);
            this.executeAction();
        }
    }

    checkExternalSignatureBook() {
        this.loading = true;

        return new Promise((resolve) => {
            this.http.post(`../rest/resourcesList/users/${this.data.userId}/groups/${this.data.groupId}/baskets/${this.data.basketId}/checkExternalSignatoryBook`, { resources: this.data.resIds }).pipe(
                tap((data: any) => {
                    this.additionalsInfos = data.additionalsInfos;
                    if (this.additionalsInfos.attachments.length > 0) {
                        this.signatoryBookEnabled = data.signatureBookEnabled;
                        this.resourcesMailing = data.additionalsInfos.attachments.filter((element: any) => element.mailing);
                        data.availableResources.filter((element: any) => !element.mainDocument).forEach((element: any) => {
                            this.toggleDocToSign(true, element, false);
                        });
                    }
                    this.errors = data.errors;
                    resolve(true);
                }),
                finalize(() => this.loading = false),
                catchError((err: any) => {
                    this.notify.handleSoftErrors(err);
                    this.dialogRef.close();
                    return of(false);
                })
            ).subscribe();
        });
    }

    executeAction() {
        const realResSelected: string[] = this[this.signatoryBookEnabled].getRessources();
        const datas: any = this[this.signatoryBookEnabled].getDatas();

        this.http.put(this.data.processActionRoute, { resources: realResSelected, note: this.noteEditor.getNote(), data: datas }).pipe(
            tap((data: any) => {
                if (!data) {
                    this.dialogRef.close(realResSelected);
                }
                if (data && data.errors != null) {
                    this.notify.error(data.errors);
                }
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    isValidAction() {
        if (this[this.signatoryBookEnabled] !== undefined) {
            return this[this.signatoryBookEnabled].isValidParaph();
        } else {
            return false;
        }
    }

    toggleIntegration(integrationId: string) {
        this.resourcesToSign = [];
        this.http.put('../rest/resourcesList/integrations', { resources: this.data.resIds, integrations: { [integrationId]: !this.data.resource.integrations[integrationId] } }).pipe(
            tap(async () => {
                this.data.resource.integrations[integrationId] = !this.data.resource.integrations[integrationId];

                if (!this.mainDocumentSigned) {
                    this.toggleDocToSign(this.data.resource.integrations[integrationId], this.data.resource, true);
                }
                await this.checkExternalSignatureBook();
                this.changeDetectorRef.detectChanges();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    toggleDocToSign(state: boolean, document: any, mainDocument: boolean = true) {
        if (state) {
            this.resourcesToSign.push(
                {
                    resId: document.resId,
                    chrono: document.chrono,
                    title: document.subject,
                    mainDocument: mainDocument,
                });
        } else {
            const index = this.resourcesToSign.map((item: any) => `${item.resId}_${item.mainDocument}`).indexOf(`${document.resId}_${mainDocument}`);
            this.resourcesToSign.splice(index, 1);
        }
    }
}
