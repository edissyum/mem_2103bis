import { Component, OnInit } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { KeyValue } from '@angular/common';
import { FormControl, Validators } from '@angular/forms';
import { catchError, debounceTime, filter, map, tap } from 'rxjs/operators';
import { ColorEvent } from 'ngx-color';
import { FunctionsService } from '@service/functions.service';
import {
    amber,
    blue,
    blueGrey,
    brown,
    cyan,
    deepOrange,
    deepPurple,
    green,
    indigo,
    lightBlue,
    lightGreen,
    lime,
    orange,
    pink,
    purple,
    red,
    teal,
    yellow,
} from 'material-colors';
import { ConfirmComponent } from '@plugins/modal/confirm.component';
import { of } from 'rxjs';
import { NotificationService } from '@service/notification/notification.service';
import { MatDialog } from '@angular/material/dialog';

@Component({
    selector: 'app-other-parameters',
    templateUrl: './other-parameters.component.html',
    styleUrls: ['./other-parameters.component.scss'],
})
export class OtherParametersComponent implements OnInit {
    // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
    hasError      : boolean = false;
    loading       : boolean = false;
    archivalError : string  = '';
    result        : string  = '';
    attachmentsHostsEnabled = [];
    attachmentsHostsConf: any = {
        nextcloud: {
            byDefault: new FormControl(false),
            url: new FormControl(''),
            username: new FormControl(''),
            password: new FormControl(''),
            folderNextcloud : new FormControl(''),
            urlExpirationDate : new FormControl(''),
            textAddedAboveURLS : new FormControl('Pièces Jointes :'),
        }
    };
    // END EDISSYUM - AMO01

    editorsConf: any = {
        java: {},
        onlyoffice: {
            ssl: new FormControl(false),
            uri: new FormControl('192.168.0.11', [Validators.required]),
            port: new FormControl(8765, [Validators.required]),
            token: new FormControl(''),
            authorizationHeader: new FormControl('Authorization')
        },
        collaboraonline: {
            ssl: new FormControl(false),
            uri: new FormControl('192.168.0.11', [Validators.required]),
            port: new FormControl(9980, [Validators.required]),
        },
        office365sharepoint: {
            tenantId: new FormControl('abc-123456789-efd', [Validators.required]),
            clientId: new FormControl('abc-123456789-efd', [Validators.required]),
            clientSecret: new FormControl('abc-123456789-efd'),
            siteUrl: new FormControl('https://exemple.sharepoint.com/sites/example', [Validators.required]),
        }
    };


    addinOutlookConf = {
        indexingModelId: new FormControl(null, [Validators.required]),
        typeId: new FormControl(null, [Validators.required]),
        statusId: new FormControl(null, [Validators.required]),
        attachmentTypeId: new FormControl(null, [Validators.required]),
    };

    watermark = {
        enabled: new FormControl(true),
        text: new FormControl('Copie conforme de [alt_identifier] le [date_now] [hour_now]'),
        posX: new FormControl(30),
        posY: new FormControl(35),
        angle: new FormControl(0),
        opacity: new FormControl(0.5),
        font: new FormControl('helvetica'),
        size: new FormControl(10),
        color: new FormControl([20, 192, 30]),
    };

    editorsEnabled = [];

    fonts = [
        {
            id: 'courier',
            label: 'courier'
        },
        {
            id: 'courierB',
            label: 'courierB'
        },
        {
            id: 'courierI',
            label: 'courierI'
        },
        {
            id: 'courierBI',
            label: 'courierBI'
        },
        {
            id: 'helvetica',
            label: 'helvetica'
        },
        {
            id: 'helveticaB',
            label: 'helveticaB'
        },
        {
            id: 'helveticaI',
            label: 'helveticaI'
        },
        {
            id: 'helveticaBI',
            label: 'helveticaBI'
        },
        {
            id: 'times',
            label: 'times'
        },
        {
            id: 'timesB',
            label: 'timesB'
        },
        {
            id: 'timesI',
            label: 'timesI'
        },
        {
            id: 'timesBI',
            label: 'timesBI'
        },
        {
            id: 'symbol',
            label: 'symbol'
        },
        {
            id: 'zapfdingbats',
            label: 'zapfdingbats'
        }
    ];

    colors: string[] = [
        red['900'],
        red['700'],
        red['500'],
        red['300'],
        red['100'],
        pink['900'],
        pink['700'],
        pink['500'],
        pink['300'],
        pink['100'],
        purple['900'],
        purple['700'],
        purple['500'],
        purple['300'],
        purple['100'],
        deepPurple['900'],
        deepPurple['700'],
        deepPurple['500'],
        deepPurple['300'],
        deepPurple['100'],
        indigo['900'],
        indigo['700'],
        indigo['500'],
        indigo['300'],
        indigo['100'],
        blue['900'],
        blue['700'],
        blue['500'],
        blue['300'],
        blue['100'],
        lightBlue['900'],
        lightBlue['700'],
        lightBlue['500'],
        lightBlue['300'],
        lightBlue['100'],
        cyan['900'],
        cyan['700'],
        cyan['500'],
        cyan['300'],
        cyan['100'],
        teal['900'],
        teal['700'],
        teal['500'],
        teal['300'],
        teal['100'],
        '#194D33',
        green['700'],
        green['500'],
        green['300'],
        green['100'],
        lightGreen['900'],
        lightGreen['700'],
        lightGreen['500'],
        lightGreen['300'],
        lightGreen['100'],
        lime['900'],
        lime['700'],
        lime['500'],
        lime['300'],
        lime['100'],
        yellow['900'],
        yellow['700'],
        yellow['500'],
        yellow['300'],
        yellow['100'],
        amber['900'],
        amber['700'],
        amber['500'],
        amber['300'],
        amber['100'],
        orange['900'],
        orange['700'],
        orange['500'],
        orange['300'],
        orange['100'],
        deepOrange['900'],
        deepOrange['700'],
        deepOrange['500'],
        deepOrange['300'],
        deepOrange['100'],
        brown['900'],
        brown['700'],
        brown['500'],
        brown['300'],
        brown['100'],
        blueGrey['900'],
        blueGrey['700'],
        blueGrey['500'],
        blueGrey['300'],
        blueGrey['100'],
    ];

    indexingModels: any = [];
    doctypes: any = [];
    statuses: any = [];
    attachmentsTypes: any = [];

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        private dialog: MatDialog,
        private notify: NotificationService,
        public functions: FunctionsService,
    ) { }

    async ngOnInit() {
        this.getStatuses();
        this.getDoctypes();
        this.getIndexingModels();
        this.getAttachmentTypes();
        await this.getWatermarkConfiguration();
        await this.getEditorsConfiguration();
        await this.getAddinOutlookConfConfiguration();

        // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
        await this.getAttachmentsHostsConfiguration();
        Object.keys(this.attachmentsHostsConf).forEach(editorId => {
            Object.keys(this.attachmentsHostsConf[editorId]).forEach((elementId: any) => {
                this.attachmentsHostsConf[editorId][elementId].valueChanges
                    .pipe(
                        debounceTime(1000),
                        filter(() => this.attachmentsHostsConf[editorId][elementId].valid),
                        tap(() => {
                            this.saveAttachmentsHosts();
                        }),
                    ).subscribe();
            });
        });
        // END EDISSYUM - AMO01

        Object.keys(this.editorsConf).forEach(editorId => {
            Object.keys(this.editorsConf[editorId]).forEach((elementId: any) => {
                this.editorsConf[editorId][elementId].valueChanges
                    .pipe(
                        debounceTime(1000),
                        filter(() => this.editorsConf[editorId][elementId].valid),
                        tap(() => {
                            this.saveConfEditor();
                        }),
                    ).subscribe();
            });
        });
        Object.keys(this.watermark).forEach(elemId => {
            this.watermark[elemId].valueChanges
                .pipe(
                    debounceTime(1000),
                    tap((value: any) => {
                        this.saveWatermarkConf();
                    }),
                ).subscribe();
        });

        Object.keys(this.addinOutlookConf).forEach(elemId => {
            this.addinOutlookConf[elemId].valueChanges
                .pipe(
                    debounceTime(1000),
                    tap((value: any) => {
                        this.saveAddinOutlookConf();
                    }),
                ).subscribe();
        });
    }

    getWatermarkConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/watermark/configuration').pipe(
                tap((data: any) => {
                    if (!this.functions.empty(data.configuration)) {
                        this.watermark = {
                            enabled: new FormControl(data.configuration.enabled),
                            text: new FormControl(data.configuration.text),
                            posX: new FormControl(data.configuration.posX),
                            posY: new FormControl(data.configuration.posY),
                            angle: new FormControl(data.configuration.angle),
                            opacity: new FormControl(data.configuration.opacity),
                            font: new FormControl(data.configuration.font),
                            size: new FormControl(data.configuration.size),
                            color: new FormControl(data.configuration.color),
                        };
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getAddinOutlookConfConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/plugins/outlook/configuration').pipe(
                tap(async (data: any) => {
                    if (!this.functions.empty(data.configuration) && Object.keys(data.configuration).length > 1) {
                        this.addinOutlookConf = {
                            indexingModelId: new FormControl(data.configuration.indexingModelId),
                            typeId: new FormControl(data.configuration.typeId),
                            statusId: new FormControl(data.configuration.statusId),
                            attachmentTypeId: new FormControl(data.configuration.attachmentTypeId),
                        };
                    } else {
                        await this.setDefaultValues();
                        this.saveAddinOutlookConf();
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    getEditorsConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/configurations/admin_document_editors').pipe(
                map((data: any) => data.configuration.value),
                tap((data: any) => {
                    Object.keys(data).forEach(confId => {
                        this.editorsEnabled.push(confId);
                        Object.keys(data[confId]).forEach(itemId => {
                            console.log(confId, itemId);

                            if (!this.functions.empty(this.editorsConf[confId][itemId])) {
                                this.editorsConf[confId][itemId].setValue(data[confId][itemId]);
                            }
                        });
                    });
                    resolve(true);
                })
            ).subscribe();
        });
    }

    originalOrder = (a: KeyValue<string, any>, b: KeyValue<string, any>): number => 0;

    addEditor(id: string) {
        this.editorsEnabled.push(id);
        this.saveConfEditor();
    }

    removeEditor(index: number) {
        const dialogRef = this.dialog.open(ConfirmComponent, { panelClass: 'maarch-modal', autoFocus: false, disableClose: true, data: { title: this.translate.instant('lang.delete'), msg: this.translate.instant('lang.confirmAction') } });

        dialogRef.afterClosed().pipe(
            filter((data: string) => data === 'ok'),
            tap(() => {
                this.editorsEnabled.splice(index, 1);
                this.saveConfEditor();
            }),
            catchError((err: any) => {
                this.notify.handleSoftErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    getAvailableEditors() {
        const allEditors = Object.keys(this.editorsConf);
        const availableEditors = allEditors.filter(editor => this.editorsEnabled.indexOf(editor) === -1);
        return availableEditors;
    }

    allEditorsEnabled() {
        return Object.keys(this.editorsConf).length === this.editorsEnabled.length;
    }

    saveWatermarkConf() {
        this.http.put('../rest/watermark/configuration', this.formatWatermarkConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveAddinOutlookConf() {
        this.http.put('../rest/plugins/outlook/configuration', this.formatAddinOutlookConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    saveConfEditor() {
        this.http.put('../rest/configurations/admin_document_editors', this.formatEditorsConfig()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    // EDISSYUM - AMO01 Rajout d'une option -> mettre PJ dans mail via liens éphémères
    saveAttachmentsHosts() {
        this.http.put('../rest/configurations/admin_attachments_hosts', this.formatAttachementsHosts()).pipe(
            tap(() => {
                this.notify.success(this.translate.instant('lang.dataUpdated'));
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }

    formatAttachementsHosts() {
        const obj: any = {};
        this.attachmentsHostsEnabled.forEach(id => {
            if (this.attachmentsHostsEnabled.indexOf(id) > -1) {
                obj[id] = {};
                Object.keys(this.attachmentsHostsConf[id]).forEach(elemId => {
                    obj[id][elemId] = this.attachmentsHostsConf[id][elemId].value;
                });
            }
        });
        return obj;
    }

    getAttachmentsHostsConfiguration() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/configurations/admin_attachments_hosts').pipe(
                map((data: any) => data.configuration.value),
                tap((data: any) => {
                    if (data) {
                        Object.keys(data).forEach(confId => {
                            this.attachmentsHostsEnabled.push(confId);
                            Object.keys(data[confId]).forEach(itemId => {
                                if (!this.functions.empty(this.attachmentsHostsConf[confId][itemId])) {
                                    this.attachmentsHostsConf[confId][itemId].setValue(data[confId][itemId]);
                                }
                            });
                        });
                    }
                    resolve(true);
                })
            ).subscribe();
        });
    }

    checkNextcloudConnection() {
        this.loading = true;
        this.result = '';
        this.http.get('../rest/parameters/nextcloud/check').pipe(
            tap(() => {
                this.loading = false;
                this.hasError = false;
                this.result = this.translate.instant('lang.interconnectionNextcloudSuccess');
            }),
            catchError((err: any) => {
                this.hasError = true;
                this.loading = false;
                this.archivalError = err.error.error;
                if (this.archivalError === 'WrongUSERorPASS') {
                    this.result = this.translate.instant('lang.interconnectionNextcloudFailed') + this.translate.instant('lang.interconnectionNextcloudFailedCauseUSERorPASS');
                } else if (this.archivalError === 'WrongFolder') {
                    this.result = this.translate.instant('lang.interconnectionNextcloudFailed') + this.translate.instant('lang.interconnectionNextcloudFailedCauseFOLDER');
                } else if (this.archivalError === 'WrongURL') {
                    this.result = this.translate.instant('lang.interconnectionNextcloudFailed') + this.translate.instant('lang.interconnectionNextcloudFailedCauseURL');
                } else {
                    this.result = this.translate.instant('lang.interconnectionNextcloudFailed') + this.archivalError + this.translate.instant('lang.interconnectionNextcloudFailedCauseUNKNOWN');
                }
                return of(false);
            })
        ).subscribe();
    }

    // EDISSYUM - AMO01

    formatAddinOutlookConfig() {
        const obj: any = {};
        Object.keys(this.addinOutlookConf).forEach(elemId => {
            obj[elemId] = this.addinOutlookConf[elemId].value;

        });
        return obj;
    }

    formatWatermarkConfig() {
        const obj: any = {};
        Object.keys(this.watermark).forEach(elemId => {
            obj[elemId] = this.watermark[elemId].value;

        });
        return obj;
    }

    formatEditorsConfig() {
        const obj: any = {};
        this.editorsEnabled.forEach(id => {
            if (this.editorsEnabled.indexOf(id) > -1) {
                obj[id] = {};
                Object.keys(this.editorsConf[id]).forEach(elemId => {
                    obj[id][elemId] = this.editorsConf[id][elemId].value;
                });
            }
        });
        return obj;
    }

    handleChange($event: ColorEvent) {
        this.watermark.color.setValue([$event.color.rgb.r, $event.color.rgb.g, $event.color.rgb.b]);
    }

    getDoctypes() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/doctypes').pipe(
                tap((data: any) => {
                    let arrValues: any[] = [];
                    data.structure.forEach((doctype: any) => {
                        if (doctype['doctypes_second_level_id'] === undefined) {
                            arrValues.push({
                                id: doctype.doctypes_first_level_id,
                                label: doctype.doctypes_first_level_label,
                                title: doctype.doctypes_first_level_label,
                                disabled: true,
                                isTitle: true,
                                color: doctype.css_style
                            });
                            data.structure.filter((info: any) => info.doctypes_first_level_id === doctype.doctypes_first_level_id && info.doctypes_second_level_id !== undefined && info.description === undefined).forEach((secondDoctype: any) => {
                                arrValues.push({
                                    id: secondDoctype.doctypes_second_level_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;' + secondDoctype.doctypes_second_level_label,
                                    title: secondDoctype.doctypes_second_level_label,
                                    disabled: true,
                                    isTitle: true,
                                    color: secondDoctype.css_style
                                });
                                arrValues = arrValues.concat(data.structure.filter((infoDoctype: any) => infoDoctype.doctypes_second_level_id === secondDoctype.doctypes_second_level_id && infoDoctype.description !== undefined).map((infoType: any) => ({
                                    id: infoType.type_id,
                                    label: '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' + infoType.description,
                                    title: infoType.description,
                                    disabled: false,
                                    isTitle: false,
                                })));
                            });
                        }
                    });
                    this.doctypes = arrValues;
                    const defaultDoctype = arrValues.filter((struct: any) => !struct.disabled)[0].id;                    
                    resolve(defaultDoctype);
                })
            ).subscribe();
        });
    }

    getIndexingModels() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/indexingModels').pipe(
                tap((data: any) => {
                    this.indexingModels = data.indexingModels.filter((info: any) => info.private === false);
                    const defaultIndexingModel = data.indexingModels[0].id;
                    resolve(defaultIndexingModel);
                })
            ).subscribe();
        });
    }

    getStatuses() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/statuses').pipe(
                tap((data: any) => {
                    this.statuses = data.statuses.map((status: any) => ({
                        id: status.identifier,
                        label: status.label_status
                    }));
                    const defaultStatus = data.statuses[0].identifier;
                    resolve(defaultStatus);
                })
            ).subscribe();
        });
    }

    getAttachmentTypes() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/attachmentsTypes').pipe(
                tap((data: any) => {
                    Object.keys(data.attachmentsTypes).forEach(templateType => {
                        this.attachmentsTypes.push({
                            id: data.attachmentsTypes[templateType].id,
                            label: data.attachmentsTypes[templateType].label
                        });
                    });
                    const defAttachment: any = Object.values(data.attachmentsTypes)[0];
                    resolve(defAttachment.id);
                })
            ).subscribe();
        });
    }

    setDefaultValues() {
        return new Promise((resolve, reject) => {
            Promise.all([this.getIndexingModels(), this.getDoctypes(), this.getStatuses(), this.getAttachmentTypes()]).then((data: any) => {
                this.addinOutlookConf.indexingModelId.setValue(data[0]);
                this.addinOutlookConf.typeId.setValue(data[1]);
                this.addinOutlookConf.statusId.setValue(data[2]);
                this.addinOutlookConf.attachmentTypeId.setValue(data[3]);
                resolve(true);
            });
        });
    }
}
