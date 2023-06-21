import { Component, Input, OnInit, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { CdkDragDrop, moveItemInArray } from '@angular/cdk/drag-drop';
import { FunctionsService } from '@service/functions.service';
import { tap, catchError } from 'rxjs/operators';
import { FormControl } from '@angular/forms';
import { ScanPipe } from 'ngx-pipes';
import { Observable, of } from 'rxjs';
import { MatDialog } from '@angular/material/dialog';

@Component({
    selector: 'app-external-visa-workflow',
    templateUrl: 'external-visa-workflow.component.html',
    styleUrls: ['external-visa-workflow.component.scss'],
    providers: [ScanPipe]
})
export class ExternalVisaWorkflowComponent implements OnInit {

    @Input() injectDatas: any;
    @Input() adminMode: boolean;
    @Input() resId: number = null;

    @Output() workflowUpdated = new EventEmitter<any>();

    visaWorkflow: any = {
        roles: ['sign', 'visa'],
        items: []
    };
    visaWorkflowClone: any = [];
    visaTemplates: any = {
        private: [],
        public: []
    };

    signVisaUsers: any = [];
    filteredPrivateModels: Observable<string[]>;

    loading: boolean = false;
    data: any;

    searchVisaSignUser = new FormControl();

    loadedInConstructor: boolean = false;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private notify: NotificationService,
        public functions: FunctionsService,
        public dialog: MatDialog
    ) { }

    ngOnInit(): void { }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            if (this.canMoveUserExtParaph(event)) {
                moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
            } else {
                this.notify.error(this.translate.instant('lang.errorUserSignType'));
            }
        }
    }

    canMoveUserExtParaph(ev: any) {
        const newWorkflow = this.array_move(this.visaWorkflow.items.slice(), ev.currentIndex, ev.previousIndex);
        const res = this.isValidExtWorkflow(newWorkflow);
        return res;
    }

    array_move(arr: any, old_index: number, new_index: number) {
        if (new_index >= arr.length) {
            let k = new_index - arr.length + 1;
            while (k--) {
                arr.push(undefined);
            }
        }
        arr.splice(new_index, 0, arr.splice(old_index, 1)[0]);
        return arr;
    }

    isValidExtWorkflow(workflow: any = this.visaWorkflow) {
        let res: boolean = true;
        workflow.forEach((item: any, indexUserRgs: number) => {
            if (['visa', 'stamp'].indexOf(item.role) === -1) {
                if (workflow.filter((itemUserStamp: any, indexUserStamp: number) => indexUserStamp > indexUserRgs && itemUserStamp.role === 'stamp').length > 0) {
                    res = false;
                }
            }
        });
        return res;
    }

    loadListModel(entityId: number) {
        this.loading = true;

        this.visaWorkflow.items = [];

        const route = `../rest/listTemplates/entities/${entityId}?type=visaCircuit&maarchParapheur=true`;

        return new Promise((resolve) => {
            this.http.get(route)
                .subscribe((data: any) => {
                    if (data.listTemplates[0]) {
                        this.visaWorkflow.items = data.listTemplates[0].items.map((item: any) => ({
                            ...item,
                            item_entity: item.descriptionToDisplay,
                            requested_signature: item.item_mode !== 'visa',
                            currentRole: item.item_mode
                        }));
                    }
                    this.visaWorkflow.items.forEach((element: any, key: number) => {
                        if (!this.functions.empty(element['externalId'])) {
                            this.getMaarchParapheurUserAvatar(element.externalId.maarchParapheur, key);
                        }
                    });
                    this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                    this.loading = false;
                    resolve(true);
                });
        });
    }

    loadWorkflowMaarchParapheur(attachmentId: number, type: string) {
        this.loading = true;
        this.visaWorkflow.items = [];
        this.http.get(`../rest/documents/${attachmentId}/maarchParapheurWorkflow?type=${type}`)
            .subscribe((data: any) => {
                data.workflow.forEach((element: any, key: any) => {
                    const user = {
                        'listinstance_id': key,
                        'id': element.userId,
                        'labelToDisplay': element.userDisplay,
                        'requested_signature': element.mode !== 'visa',
                        'process_date': this.functions.formatFrenchDateToTechnicalDate(element.processDate),
                        'picture': '',
                        'hasPrivilege': true,
                        'isValid': true,
                        'delegatedBy': null,
                        'role': element.mode !== 'visa' ? element.signatureMode : 'visa',
                        'status': element.status
                    };
                    this.visaWorkflow.items.push(user);
                    this.getMaarchParapheurUserAvatar(element.userId, key);
                });
                this.loading = false;
            }, (err: any) => {
                this.notify.handleErrors(err);
            });
    }

    deleteItem(index: number) {
        this.visaWorkflow.items.splice(index, 1);
        this.workflowUpdated.emit(this.visaWorkflow.items);
    }

    getVisaCount() {
        return this.visaWorkflow.items.length;
    }

    getWorkflow() {
        return this.visaWorkflow.items;
    }

    getCurrentVisaUserIndex() {
        if (this.getLastVisaUser().listinstance_id === undefined) {
            const index = 0;
            return this.getRealIndex(index);
        } else {
            let index = this.visaWorkflow.items.map((item: any) => item.listinstance_id).indexOf(this.getLastVisaUser().listinstance_id);
            index++;
            return this.getRealIndex(index);
        }
    }

    getFirstVisaUser() {
        return !this.functions.empty(this.visaWorkflow.items[0]) && this.visaWorkflow.items[0].isValid ? this.visaWorkflow.items[0] : '';
    }

    getNextVisaUser() {
        let index = this.getCurrentVisaUserIndex();
        index = index + 1;
        const realIndex = this.getRealIndex(index);

        return !this.functions.empty(this.visaWorkflow.items[realIndex]) ? this.visaWorkflow.items[realIndex] : '';
    }

    getLastVisaUser() {
        const arrOnlyProcess = this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date) && item.isValid);

        return !this.functions.empty(arrOnlyProcess[arrOnlyProcess.length - 1]) ? arrOnlyProcess[arrOnlyProcess.length - 1] : '';
    }

    getRealIndex(index: number) {
        while (index < this.visaWorkflow.items.length && !this.visaWorkflow.items[index].isValid) {
            index++;
        }
        return index;
    }

    checkExternalSignatoryBook() {
        return this.visaWorkflow.items.filter((item: any) => this.functions.empty(item.externalId)).map((item: any) => item.labelToDisplay);
    }

    saveVisaWorkflow(resIds: number[] = [this.resId]) {
        return new Promise((resolve, reject) => {
            if (this.visaWorkflow.items.length === 0) {
                this.http.delete(`../rest/resources/${resIds[0]}/circuits/visaCircuit`).pipe(
                    tap(() => {
                        this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                        this.notify.success(this.translate.instant('lang.visaWorkflowDeleted'));
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else if (this.isValidWorkflow()) {
                const arrVisa = resIds.map(resId => ({
                    resId: resId,
                    listInstances: this.visaWorkflow.items
                }));
                this.http.put('../rest/circuits/visaCircuit', { resources: arrVisa }).pipe(
                    tap((data: any) => {
                        this.visaWorkflowClone = JSON.parse(JSON.stringify(this.visaWorkflow.items));
                        this.notify.success(this.translate.instant('lang.visaWorkflowUpdated'));
                        resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        resolve(false);
                        return of(false);
                    })
                ).subscribe();
            } else {
                this.notify.error(this.getError());
                resolve(false);
            }
        });
    }

    addItemToWorkflow(item: any) {
        return new Promise((resolve, reject) => {
            this.visaWorkflow.items.push({
                item_id: item.id,
                item_type: 'user',
                item_entity: item.email,
                labelToDisplay: item.idToDisplay,
                externalId: item.externalId,
                difflist_type: 'VISA_CIRCUIT',
                signatory: !this.functions.empty(item.signatory) ? item.signatory : false,
                hasPrivilege: true,
                isValid: true,
                availableRoles : ['visa'].concat(item.signatureModes),
                role: item.signatureModes[item.signatureModes.length - 1]
            });
            if (!this.isValidRole(this.visaWorkflow.items.length - 1, item.signatureModes[item.signatureModes.length - 1], item.signatureModes[item.signatureModes.length - 1])) {
                this.visaWorkflow.items[this.visaWorkflow.items.length - 1].role = 'visa';
            }
            this.getMaarchParapheurUserAvatar(item.externalId.maarchParapheur, this.visaWorkflow.items.length - 1);
            this.searchVisaSignUser.reset();
            resolve(true);
        });
    }

    resetWorkflow() {
        this.visaWorkflow.items = [];
    }

    isValidWorkflow() {
        if ((this.visaWorkflow.items.filter((item: any) => item.requested_signature).length > 0 && this.visaWorkflow.items.filter((item: any) => (!item.hasPrivilege || !item.isValid) && (item.process_date === null || this.functions.empty(item.process_date))).length === 0) && this.visaWorkflow.items.length > 0) {
            return true;
        } else {
            return false;
        }
    }

    getError() {
        if (this.visaWorkflow.items.filter((item: any) => item.requested_signature).length === 0) {
            return this.translate.instant('lang.signUserRequired');
        } else if (this.visaWorkflow.items.filter((item: any) => !item.hasPrivilege).length > 0) {
            return this.translate.instant('lang.mustDeleteUsersWithNoPrivileges');
        } else if (this.visaWorkflow.items.filter((item: any) => !item.isValid && (item.process_date === null || this.functions.empty(item.process_date))).length > 0) {
            return this.translate.instant('lang.mustDeleteInvalidUsers');
        }
    }

    emptyWorkflow() {
        return this.visaWorkflow.items.length === 0;
    }

    workflowEnd() {
        if (this.visaWorkflow.items.filter((item: any) => !this.functions.empty(item.process_date)).length === this.visaWorkflow.items.length) {
            return true;
        } else {
            return false;
        }
    }

    getMaarchParapheurUserAvatar(externalId: string, key: number) {
        if (!this.functions.empty(externalId)) {
            this.http.get('../rest/maarchParapheur/user/' + externalId + '/picture')
                .subscribe((data: any) => {
                    this.visaWorkflow.items[key].picture = data.picture;
                }, (err: any) => {
                    this.notify.handleErrors(err);
                });
        }
    }

    isModified() {
        return !(this.loading || JSON.stringify(this.visaWorkflow.items) === JSON.stringify(this.visaWorkflowClone));
    }

    canManageUser() {
        if (this.adminMode) {
            return true;
        } else {
            return false;
        }
    }

    isValidRole(indexWorkflow: any, role: string, currentRole: string) {
        if (this.visaWorkflow.items.filter((item: any, index: any) => index > indexWorkflow && ['stamp'].indexOf(item.role) > -1).length > 0 && ['visa', 'stamp'].indexOf(currentRole) > -1 && ['visa', 'stamp'].indexOf(role) === -1) {
            return false;
        } else if (this.visaWorkflow.items.filter((item: any, index: any) => index < indexWorkflow && ['visa', 'stamp'].indexOf(item.role) === -1).length > 0 && role === 'stamp') {
            return false;
        } else {
            return true;
        }
    }

    setPositionsWorkfow(resource: any, positions: any) {
        this.clearOldPositionsFromResource(resource);

        if (positions.signaturePositions !== undefined) {
            Object.keys(positions.signaturePositions).forEach(key => {
                const objPos = {
                    ...positions.signaturePositions[key],
                    mainDocument : resource.mainDocument,
                    resId: resource.resId
                };
                this.visaWorkflow.items[positions.signaturePositions[key].sequence].signaturePositions.push(objPos);
            });
        }
        if (positions.datePositions !== undefined) {
            Object.keys(positions.datePositions).forEach(key => {
                const objPos = {
                    ...positions.datePositions[key],
                    mainDocument : resource.mainDocument,
                    resId: resource.resId
                };
                this.visaWorkflow.items[positions.datePositions[key].sequence].datePositions.push(objPos);
            });
        }
    }

    clearOldPositionsFromResource(resource: any) {
        this.visaWorkflow.items.forEach((user: any) => {

            if (user.signaturePositions === undefined) {
                user.signaturePositions = [];
            } else {
                const signaturePositionsToKeep = [];
                user.signaturePositions.forEach((pos: any) => {
                    if (pos.resId !== resource.resId && pos.mainDocument === resource.mainDocument) {
                        signaturePositionsToKeep.push(pos);
                    } else if (pos.mainDocument !== resource.mainDocument) {
                        signaturePositionsToKeep.push(pos);
                    }
                });
                user.signaturePositions = signaturePositionsToKeep;
            }

            if (user.datePositions === undefined) {
                user.datePositions = [];
            } else {
                const datePositionsToKeep = [];
                user.datePositions.forEach((pos: any) => {
                    if (pos.resId !== resource.resId && pos.mainDocument === resource.mainDocument) {
                        datePositionsToKeep.push(pos);
                    } else if (pos.mainDocument !== resource.mainDocument) {
                        datePositionsToKeep.push(pos);
                    }
                });
                user.datePositions = datePositionsToKeep;
            }
        });
    }

    getDocumentsFromPositions() {
        const documents: any[] = [];
        this.visaWorkflow.items.forEach((user: any) => {
            user.signaturePositions?.forEach(element => {
                documents.push({
                    resId: element.resId,
                    mainDocument: element.mainDocument
                });
            });
            user.datePositions?.forEach(element => {
                documents.push({
                    resId: element.resId,
                    mainDocument: element.mainDocument
                });
            });
        });
        return documents;
    }
}
