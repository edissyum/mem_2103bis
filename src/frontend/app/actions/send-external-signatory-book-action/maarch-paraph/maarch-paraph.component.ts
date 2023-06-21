import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { SignaturePositionComponent } from './signature-position/signature-position.component';
import { catchError, filter, finalize, tap } from 'rxjs/operators';
import { of } from 'rxjs';
import { FunctionsService } from '@service/functions.service';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';
import { ExternalVisaWorkflowComponent } from '@appRoot/visa/externalVisaWorkflow/external-visa-workflow.component';

@Component({
    selector: 'app-maarch-paraph',
    templateUrl: 'maarch-paraph.component.html',
    styleUrls: ['maarch-paraph.component.scss'],
})
export class MaarchParaphComponent implements OnInit {

    @ViewChild('appExternalVisaWorkflow', { static: true }) appExternalVisaWorkflow: ExternalVisaWorkflowComponent;

    @Input() resIds: number[] = [];
    @Input() resourcesToSign: any[] = [];
    @Input() additionalsInfos: any;
    @Input() externalSignatoryBookDatas: any;

    loading: boolean = false;

    currentAccount: any = null;
    usersWorkflowList: any[] = [];

    signaturePositions: any = {};

    injectDatasParam = {
        resId: 0,
        editable: true
    };

    constructor(
        public translate: TranslateService,
        private notify: NotificationService,
        public http: HttpClient,
        private functions: FunctionsService,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void {
        if (typeof this.additionalsInfos.destinationId !== 'undefined' && this.additionalsInfos.destinationId !== '') {
            setTimeout(() => {
                this.appExternalVisaWorkflow.loadListModel(this.additionalsInfos.destinationId);
            }, 0);
        }
    }

    isValidParaph() {
        if (this.additionalsInfos.attachments.length === 0 || this.appExternalVisaWorkflow.getWorkflow().length === 0 || this.appExternalVisaWorkflow.checkExternalSignatoryBook().length > 0 || this.resourcesToSign.length === 0) {
            return false;
        } else {
            return true;
        }
    }

    getRessources() {
        return this.additionalsInfos.attachments.map((e: any) => e.res_id);
    }

    getDatas() {
        const formatedData: any = { steps: [] };
        const workflow = this.appExternalVisaWorkflow.getWorkflow();

        this.resourcesToSign.forEach((resource: any) => {
            workflow.forEach((element: any, index: number) => {
                formatedData['steps'].push(
                    {
                        'resId': resource.resId,
                        'mainDocument': resource.mainDocument,
                        'externalId': element.externalId.maarchParapheur,
                        'sequence': index,
                        'action': element.role === 'visa' ? 'visa' : 'sign',
                        'signatureMode': element.role,
                        'signaturePositions': element.signaturePositions !== undefined ? this.formatPositions(element.signaturePositions.filter((pos: any) => pos.resId === resource.resId && pos.mainDocument === resource.mainDocument)) : [],
                        'datePositions': element.datePositions !== undefined ? this.formatPositions(element.datePositions.filter((pos: any) => pos.resId === resource.resId && pos.mainDocument === resource.mainDocument)) : []
                    }
                );
            });
        });
        return formatedData;
    }

    formatPositions(position: any) {
        delete position.mainDocument;
        delete position.resId;
        return position;
    }

    openSignaturePosition(resource: any) {
        const dialogRef = this.dialog.open(SignaturePositionComponent, {
            height: '99vh',
            panelClass: 'maarch-modal',
            disableClose: true,
            data: {
                resource: resource,
                workflow: this.appExternalVisaWorkflow.getWorkflow()
            }
        });
        dialogRef.afterClosed().pipe(
            filter((res: any) => !this.functions.empty(res)),
            tap((res: any) => {
                this.appExternalVisaWorkflow.setPositionsWorkfow(resource, res);
            }),
            finalize(() => this.loading = false),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    hasPositions(resource: any) {
        return this.appExternalVisaWorkflow?.getDocumentsFromPositions().filter((document: any) => document.resId === resource.resId && document.mainDocument === resource.mainDocument).length > 0;
    }
}
