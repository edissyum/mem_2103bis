import { Component, OnInit, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { MatSidenav } from '@angular/material/sidenav';
import { HeaderService } from '@service/header.service';
import { AppService } from '@service/app.service';
import { tap, catchError } from 'rxjs/operators';
import { FunctionsService } from '@service/functions.service';
import { FormControl } from '@angular/forms';
import { ActionPagesService } from '@service/actionPages.service';
import { of } from 'rxjs';


@Component({
    templateUrl: 'action-administration.component.html'
})
export class ActionAdministrationComponent implements OnInit {

    @ViewChild('snav2', { static: true }) public sidenavRight: MatSidenav;

    creationMode: boolean;
    action: any = {};
    statuses: any[] = [];
    actionPages: any[] = [];
    categoriesList: any[] = [];
    keywordsList: any[] = [];

    group: any[] = [];

    loading: boolean = false;
    availableCustomFields: Array<any> = [];
    customFieldsFormControl = new FormControl({ value: '', disabled: false });
    selectedFieldsValue: Array<any> = [];
    selectedFieldsId: Array<any> = [];
    selectedValue: any;
    arMode: any;
    canAddCopies: boolean;
    successStatus: any;
    errorStatus: any;
    intermediateStatus: any;

    selectActionPageId = new FormControl();
    selectStatusId = new FormControl();
    selectSuccessStatusId = new FormControl();
    selectErrorStatusId = new FormControl();
    selectIntermidiateStatusId = new FormControl();

    intermediateStatusActions = ['sendToRecordManagement', 'sendToExternalSignatureBook', 'send_to_visa', 'visa_workflow', 'send_shipping'];

    mailevaStatus: any[] = [
        {
            id: 'ON_STATUS_ACCEPTED',
            label: this.translate.instant('lang.ON_STATUS_ACCEPTED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_REJECTED',
            label: this.translate.instant('lang.ON_STATUS_REJECTED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_PROCESSED',
            label: this.translate.instant('lang.ON_STATUS_PROCESSED'),
            actionStatus: null,
            disabled: false
        },
        {
            id: 'ON_STATUS_ARCHIVED',
            label: this.translate.instant('lang.ON_STATUS_ARCHIVED'),
            actionStatus: null,
            disabled: false
        },
    ];

    intermediateSelectedStatus: any[] = [];
    finalSelectedStatus: any[] = [];
    errorSelectedStatus: any[] = [];

    intermediateStatusParams: any = {};
    finalStatusParams: any = {};
    errorStatusParams: any = {};


    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private route: ActivatedRoute,
        private router: Router,
        private notify: NotificationService,
        private headerService: HeaderService,
        public appService: AppService,
        public functions: FunctionsService,
        public actionPagesService: ActionPagesService) { }

    ngOnInit(): void {
        this.loading = true;

        this.setIntermediateStatus();
        this.setFinalStatus();
        this.setErrorStatus();

        this.route.params.subscribe(params => {

            if (typeof params['id'] === 'undefined') {

                this.creationMode = true;

                this.http.get('../rest/initAction')
                    .subscribe((data: any) => {
                        this.action = data.action;
                        this.action.actionPageId = 'confirm_status';
                        this.selectActionPageId.setValue('confirm_status');
                        this.selectStatusId.setValue(this.action.id_status);
                        this.categoriesList = data.categoriesList;
                        this.statuses = data.statuses.map((status: any) => ({
                            id: status.id,
                            label: status.label_status
                        }));

                        this.actionPages = this.actionPagesService.getAllActionPages();
                        this.actionPages.map(action => action.category).filter((cat, index, self) => self.indexOf(cat) === index).forEach(element => {
                            this.group.push({
                                id : element,
                                label : this.translate.instant('lang.' + element)
                            });
                        });

                        this.keywordsList = data.keywordsList;
                        this.headerService.setHeader(this.translate.instant('lang.actionCreation'));
                        this.loading = false;
                    });
            } else {
                this.creationMode = false;
                this.intermediateSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'intermediateStatus').map((el: any) => el.id);
                this.finalSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'finalStatus').map((el: any) => el.id);
                this.errorSelectedStatus = this.mailevaStatus.filter((item: any) => item.actionStatus === 'errorStatus').map((el: any) => el.id);
                this.http.get('../rest/actions/' + params['id'])
                    .subscribe(async (data: any) => {
                        this.action = data.action;
                        const currentAction = this.actionPagesService.getActionPageByComponent(this.action.component);
                        this.action.actionPageId = currentAction?.id;
                        this.selectActionPageId.setValue(this.action.actionPageId);
                        this.selectStatusId.setValue(this.action.id_status);
                        this.categoriesList = data.categoriesList;
                        this.statuses = data.statuses.map((status: any) => ({
                            id: status.id,
                            label: status.label_status
                        }));
                        this.actionPages = this.actionPagesService.getAllActionPages();
                        this.actionPages.map(action => action.category).filter((cat, index, self) => self.indexOf(cat) === index).forEach(element => {
                            this.group.push({
                                id : element,
                                label : this.translate.instant('lang.' + element)
                            });
                        });
                        this.keywordsList = data.keywordsList;
                        this.headerService.setHeader(this.translate.instant('lang.actionCreation'), data.action.label_action);
                        await this.getCustomFields();
                        this.loading = false;
                        if (this.action.actionPageId === 'close_mail') {
                            this.customFieldsFormControl = new FormControl({ value: this.action.parameters.requiredFields, disabled: false });
                            this.selectedFieldsId = [];
                            if (this.action.parameters.requiredFields) {
                                this.selectedFieldsId = this.action.parameters.requiredFields;
                            }
                            this.selectedFieldsId.forEach((element: any) => {
                                this.availableCustomFields.forEach((availableElement: any) => {
                                    if (availableElement.id === element) {
                                        this.selectedFieldsValue.push(availableElement.label);
                                    }
                                });
                            });
                        } else if (this.action.actionPageId === 'create_acknowledgement_receipt') {
                            this.arMode = this.action.parameters.mode;
                            this.canAddCopies = this.action.parameters.canAddCopies;
                        } else if (this.action.actionPageId === 'send_shipping') {
                            if (!this.functions.empty(this.action.parameters.intermediateStatus)) {
                                this.selectIntermidiateStatusId.setValue(this.action.parameters.intermediateStatus.actionStatus);
                                this.selectSuccessStatusId.setValue(this.action.parameters.finalStatus.actionStatus);
                                this.selectErrorStatusId.setValue(this.action.parameters.errorStatus.actionStatus);
                                this.getSelectedStatus(this.action.parameters.intermediateStatus.mailevaStatus, 'intermediateStatus');
                                this.getSelectedStatus(this.action.parameters.finalStatus.mailevaStatus, 'finalStatus');
                                this.getSelectedStatus(this.action.parameters.errorStatus.mailevaStatus, 'errorStatus');
                            }
                        } else if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
                            this.selectSuccessStatusId.setValue(this.action.parameters.successStatus);
                            this.selectErrorStatusId.setValue(this.action.parameters.errorStatus);
                            this.errorStatus = this.action.parameters.errorStatus;
                            this.successStatus = this.action.parameters.successStatus;
                        }
                    });
            }
        });
    }

    getCustomFields() {
        this.action.actionPageId = this.selectActionPageId.value;
        this.action.actionPageGroup = this.actionPages.filter(action => action.id === this.action.actionPageId)[0]?.category;

        if (this.action.actionPageGroup === 'registeredMail') {
            this.action.actionCategories = ['registeredMail'];
        }

        if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
            this.selectSuccessStatusId.setValue('_NOSTATUS_');
            this.selectErrorStatusId.setValue('_NOSTATUS_');
            this.selectIntermidiateStatusId.setValue('_NOSTATUS_');
        }

        return new Promise((resolve) => {
            if (this.action.actionPageId === 'close_mail' && this.functions.empty(this.availableCustomFields)) {
                this.http.get('../rest/customFields').pipe(
                    tap((data: any) => {
                        this.availableCustomFields = data.customFields.map((info: any) => {
                            info.id = 'indexingCustomField_' + info.id;
                            return info;
                        });
                        return resolve(true);
                    }),
                    catchError((err: any) => {
                        this.notify.handleSoftErrors(err);
                        return of(false);
                    })
                ).subscribe();
            } else {
                resolve(true);
            }
        });

    }

    getSelectedFields() {
        this.availableCustomFields.forEach((element: any) => {
            if (element.id === this.customFieldsFormControl.value) {
                this.selectedValue = element;
            }
        });
        if (this.selectedFieldsId.indexOf(this.customFieldsFormControl.value) < 0) {
            this.selectedFieldsValue.push(this.selectedValue.label);
            this.selectedFieldsId.push(this.customFieldsFormControl.value);
        }
        this.customFieldsFormControl.reset();
    }

    removeSelectedFields(index: number) {
        this.selectedFieldsValue.splice(index, 1);
        this.selectedFieldsId.splice(index, 1);
    }

    onSubmit() {
        if (this.action.actionPageId === 'close_mail') {
            this.action.parameters = { requiredFields: this.selectedFieldsId };
        } else if (this.action.actionPageId === 'create_acknowledgement_receipt') {
            this.action.parameters = { mode: this.arMode, canAddCopies : this.canAddCopies };
        } else if (this.action.actionPageId === 'send_shipping') {
            const intermediateStatus = {
                actionStatus: this.selectIntermidiateStatusId.value,
                mailevaStatus: this.intermediateSelectedStatus
            };
            const finalStatus = {
                actionStatus: this.selectSuccessStatusId.value,
                mailevaStatus: this.finalSelectedStatus
            };
            const errorStatus = {
                actionStatus: this.selectErrorStatusId.value,
                mailevaStatus: this.errorSelectedStatus
            };
            this.action.parameters = {
                intermediateStatus: intermediateStatus,
                finalStatus: finalStatus,
                errorStatus: errorStatus
            };
        } else if (this.intermediateStatusActions.indexOf(this.action.actionPageId) !== -1) {
            this.action.parameters = { successStatus: this.successStatus, errorStatus: this.errorStatus };
        }

        this.action.action_page = this.action.actionPageId;
        this.action.component = this.actionPagesService.getAllActionPages(this.action.actionPageId).component;
        if (this.creationMode) {
            this.http.post('../rest/actions', this.action)
                .subscribe(() => {
                    this.router.navigate(['/administration/actions']);
                    this.notify.success(this.translate.instant('lang.actionAdded'));

                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        } else {
            this.http.put('../rest/actions/' + this.action.id, this.action)
                .subscribe(() => {
                    this.router.navigate(['/administration/actions']);
                    this.notify.success(this.translate.instant('lang.actionUpdated'));

                }, (err) => {
                    this.notify.error(err.error.errors);
                });
        }
    }

    getSelectedStatus(mailevaStatus: any[], actionStatus: string) {
        if (actionStatus === 'intermediateStatus') {
            this.checkSelection(mailevaStatus, 'intermediateStatus');
            this.setIntermediateStatus(mailevaStatus);
            this.finalStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled ? true : false;
            });
            this.errorStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled ? true : false;
            });

        } else if (actionStatus === 'finalStatus') {
            this.checkSelection(mailevaStatus, 'finalStatus');
            this.setFinalStatus(mailevaStatus);
            this.intermediateStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
            this.errorStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
        } else if (actionStatus === 'errorStatus') {
            this.checkSelection(mailevaStatus, 'errorStatus');
            this.setErrorStatus(mailevaStatus);
            this.intermediateStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
            this.finalStatusParams.data.forEach((element: any) => {
                element.disabled = mailevaStatus.indexOf(element.id) > - 1 || element.disabled  ? true : false;
            });
        }
    }

    setIntermediateStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.intermediateStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'intermediateStatus' : null;
            });
            this.intermediateSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.intermediateStatusParams = {
                id: 'intermediateStatus',
                data: data
            };
        }
    }

    setFinalStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.finalStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'finalStatus' : null;
            });
            this.finalSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.finalStatusParams = {
                id: 'finalStatus',
                data: data
            };
        }
    }

    setErrorStatus(mailevaStatus: any[] = null) {
        if (mailevaStatus !== null) {
            const data: any[] = this.errorStatusParams.data;
            data.forEach((element: any, index: number) => {
                element.actionStatus = mailevaStatus.indexOf(element.id) > -1 ? 'errorStatus' : null;
            });
            this.errorSelectedStatus = mailevaStatus;
        } else {
            const data: any = JSON.parse(JSON.stringify(this.mailevaStatus));
            this.errorStatusParams = {
                id: 'errorStatus',
                data: data
            };
        }
    }

    checkSelection(mailevaStatus: any[], actionStatus: string) {
        if (actionStatus === 'intermediateStatus') {
            const array: any[] = this.intermediateStatusParams.data.filter((item: any) => item.actionStatus === 'intermediateStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.finalStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.errorStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        } else if (actionStatus === 'finalStatus') {
            const array: any[] = this.finalStatusParams.data.filter((item: any) => item.actionStatus === 'finalStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.intermediateStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.errorStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        } else if (actionStatus === 'errorStatus') {
            const array: any[] = this.errorStatusParams.data.filter((item: any) => item.actionStatus === 'errorStatus').map((el: any) => el.id);
            const deselectedItem: string = array.filter((element: any) => !mailevaStatus.includes(element)).toString();
            if (!this.functions.empty(deselectedItem)) {
                this.intermediateStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
                this.finalStatusParams.data.find((el: any) => el.id === deselectedItem).disabled = false;
            }
        }
    }
}
