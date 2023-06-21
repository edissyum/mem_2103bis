import {Component, OnInit, Input, Output, EventEmitter, ViewChild, TemplateRef, ViewContainerRef} from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { NotificationService } from '@service/notification/notification.service';
import { FormControl } from '@angular/forms';
import { CdkDragDrop, moveItemInArray, transferArrayItem } from '@angular/cdk/drag-drop';
import { startWith, map, tap, catchError } from 'rxjs/operators';
import { Observable, of } from 'rxjs';
import { AppService } from '@service/app.service';
import { HeaderService } from '@service/header.service';
import { FunctionsService } from '@service/functions.service';
import { ContactService } from '@service/contact.service'; // EDISSYUM - NCH01 Fenetre de recherche de contacts

declare let $: any;


@Component({
    templateUrl: 'contact-search-administration.component.html',
    styleUrls: ['contact-search-administration.component.scss'],
    providers: [ContactService]
})

export class ContactSearchAdministrationComponent implements OnInit {

    @ViewChild('adminMenuTemplate', { static: true }) adminMenuTemplate: TemplateRef<any>;

    subMenus: any[] = [];
    loading: boolean = true;

    displayedMainData: any = [
        {
            'value': 'contact_id',
            'label': this.translate.instant('lang.contactId'),
            'cssClasses': ['align_centerData', 'normalData'],
            'icon': ''
        },
        {
            'value': 'user',
            'label': 'Nom Prénom (Organisation)',
            'cssClasses': ['longData'],
            'icon': ''
        },
    ];

    availableData: any = [
        {
            'value': 'getCivility',
            'label': this.translate.instant('lang.contactsParameters_civility'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-user'
        },
        {
            'value': 'getEmail',
            'label': this.translate.instant('lang.contactsParameters_email'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-envelope'
        },
        {
            'value': 'getPhone',
            'label': this.translate.instant('lang.contactsParameters_phone'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-phone'
        },
        {
            'value': 'getDepartment',
            'label': this.translate.instant('lang.contactsParameters_department'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-user'
        },
        {
            'value': 'getFunction',
            'label': this.translate.instant('lang.contactsParameters_function'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-user'
        },
        {
            'value': 'getCreationDate',
            'label': this.translate.instant('lang.getCreationDate'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-calendar'
        },
        {
            'value': 'getModificationDate',
            'label': this.translate.instant('lang.getModificationDate'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-calendar-check'
        },
        {
            'value': 'getAddressNumber',
            'label': this.translate.instant('lang.contactsParameters_addressNumber'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressStreet',
            'label': this.translate.instant('lang.contactsParameters_addressStreet'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressAdditional1',
            'label': this.translate.instant('lang.contactsParameters_addressAdditional1'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressAdditional2',
            'label': this.translate.instant('lang.contactsParameters_addressAdditional2'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressPostCode',
            'label': this.translate.instant('lang.contactsParameters_addressPostcode'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressTown',
            'label': this.translate.instant('lang.contactsParameters_addressTown'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
        {
            'value': 'getAddressCountry',
            'label': this.translate.instant('lang.contactsParameters_addressCountry'),
            'cssClasses': ['align_leftData'],
            'icon': 'fa-map-marker-alt'
        },
    ];
    availableDataClone: any[] = [];
    displayedSecondaryData: any[] = [];
    displayedSecondaryDataClone: any[] = [];

    displayMode: string = 'label';
    dataControl = new FormControl();
    filteredDataOptions: Observable<any[]>;

    templateDisplayedSecondaryData: number[] = [2, 3, 4, 5, 6, 7];
    selectedTemplateDisplayedSecondaryData: number = 7;
    selectedTemplateDisplayedSecondaryDataClone: number = 7;

    searchContact: any = { listDisplay: {} };

    constructor(public translate: TranslateService, private viewContainerRef: ViewContainerRef, public contactService: ContactService, public http: HttpClient, private notify: NotificationService, public appService: AppService, public headerService: HeaderService, private functions: FunctionsService) {
        this.subMenus = contactService.getAdminMenu();
    }

    async ngOnInit(): Promise<void> {
        this.headerService.setHeader(this.translate.instant('lang.searchContactAdministration'));
        this.headerService.injectInSideBarLeft(this.adminMenuTemplate, this.viewContainerRef, 'adminMenu');
        await this.initCustomFields();
        await this.getTemplate();

        this.availableDataClone = JSON.parse(JSON.stringify(this.availableData));
        this.selectedTemplateDisplayedSecondaryDataClone = this.selectedTemplateDisplayedSecondaryData;
        this.displayedSecondaryDataClone = JSON.parse(JSON.stringify(this.displayedSecondaryData));

        setTimeout(() => {
            this.filteredDataOptions = this.dataControl.valueChanges
                .pipe(
                    startWith(''),
                    map(value => this._filterData(value))
                );
        }, 0);
        this.loading = false;
    }

    initCustomFields() {
        return new Promise((resolve, reject) => {
            this.http.get('../rest/contactsCustomFields').pipe(
                map((customData: any) => {
                    customData.customFields = customData.customFields.map((info: any) => ({
                        'value': 'contactCustomField_' + info.id,
                        'label': info.label,
                        'cssClasses': ['align_leftData'],
                        'icon': 'fa-hashtag'
                    }));
                    return customData.customFields;
                }),
                tap((customs) => {
                    this.availableData = this.availableData.concat(customs);
                    resolve(true);

                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    setStyle(item: any, value: string) {
        const typeFont = value.split('_');

        if (typeFont.length === 2) {
            item.cssClasses.forEach((element: any, it: number) => {
                if (element.includes(typeFont[0]) && element !== value) {
                    item.cssClasses.splice(it, 1);
                }
            });
        }

        const index = item.cssClasses.indexOf(value);

        if (index === -1) {
            item.cssClasses.push(value);
        } else {
            item.cssClasses.splice(index, 1);
        }
    }

    addData(id: any) {
        const i = this.availableData.map((e: any) => e.value).indexOf(id);

        this.displayedSecondaryData.push(this.availableData.filter((item: any) => item.value === id)[0]);

        this.availableData.splice(i, 1);

        $('#availableData').blur();
        this.dataControl.setValue('');
    }

    removeData(rmData: any, i: number) {
        this.availableData.push(rmData);
        this.displayedSecondaryData.splice(i, 1);
        this.dataControl.setValue('');
    }

    removeAllData() {
        this.displayedSecondaryData = this.displayedSecondaryData.concat();
        this.availableData = this.availableData.concat(this.displayedSecondaryData);
        this.dataControl.setValue('');
        this.displayedSecondaryData = [];
    }

    drop(event: CdkDragDrop<string[]>) {
        if (event.previousContainer === event.container) {
            moveItemInArray(event.container.data, event.previousIndex, event.currentIndex);
        } else {
            transferArrayItem(event.previousContainer.data, event.container.data, event.previousIndex, event.currentIndex - 1);

            this.displayedSecondaryData.forEach((subArray: any, index: any) => {
                if (subArray.length > this.selectedTemplateDisplayedSecondaryData) {
                    transferArrayItem(subArray, this.displayedSecondaryData[index + 1], subArray.length, 0);
                } else if (subArray.length < this.selectedTemplateDisplayedSecondaryData && !this.functions.empty(this.displayedSecondaryData[index + 1])) {
                    transferArrayItem(this.displayedSecondaryData[index + 1], subArray, 0, subArray.length);
                }
            });
        }

    }

    getTemplate() {
        this.displayedSecondaryData = [];

        return new Promise((resolve, reject) => {
            this.http.get('../rest/searchContacts/configuration').pipe(
                tap((templateData: any) => {
                    this.selectedTemplateDisplayedSecondaryData = templateData.configuration.listDisplay.templateColumns;
                    templateData.configuration.listDisplay.subInfos.forEach((element: any) => {
                        this.addData(element.value);
                        this.displayedSecondaryData[this.displayedSecondaryData.length - 1].cssClasses = element.cssClasses;
                    });
                    resolve(true);
                }),
                catchError((err: any) => {
                    this.notify.handleErrors(err);
                    return of(false);
                })
            ).subscribe();
        });
    }

    saveTemplate() {
        const objToSend = {
            templateColumns: this.selectedTemplateDisplayedSecondaryData,
            subInfos: this.displayedSecondaryData
        };

        this.http.put('../rest/configurations/admin_search_contacts', { 'listDisplay': objToSend })
            .subscribe(() => {
                this.displayedSecondaryDataClone = JSON.parse(JSON.stringify(this.displayedSecondaryData));
                this.searchContact.listDisplay = this.displayedSecondaryData;
                this.selectedTemplateDisplayedSecondaryDataClone = JSON.parse(JSON.stringify(this.selectedTemplateDisplayedSecondaryData));
                this.notify.success(this.translate.instant('lang.modificationsProcessed'));
            }, (err) => {
                this.notify.error(err.error.errors);
            });
    }

    private _filterData(value: any): string[] {
        let filterValue = '';

        if (typeof value === 'string') {
            filterValue = value.toLowerCase();
        } else if (value !== null) {
            filterValue = value.label.toLowerCase();
        }
        return this.availableData.filter((option: any) => option.label.toLowerCase().includes(filterValue));
    }

    checkModif() {
        if (JSON.stringify(this.displayedSecondaryData) === JSON.stringify(this.displayedSecondaryDataClone) && JSON.stringify(this.selectedTemplateDisplayedSecondaryData) === JSON.stringify(this.selectedTemplateDisplayedSecondaryDataClone)) {
            return true;
        } else {
            return false;
        }
    }

    cancelModification() {
        this.displayedSecondaryData = JSON.parse(JSON.stringify(this.displayedSecondaryDataClone));
        this.availableData = JSON.parse(JSON.stringify(this.availableDataClone));
        this.selectedTemplateDisplayedSecondaryData = JSON.parse(JSON.stringify(this.selectedTemplateDisplayedSecondaryDataClone));
        this.dataControl.setValue('');
    }
}
