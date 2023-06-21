import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { TranslateService } from '@ngx-translate/core';
import { MatAutocompleteTrigger } from '@angular/material/autocomplete';
import { MatDialog } from '@angular/material/dialog';
import { MatSidenav } from '@angular/material/sidenav';
import { Observable, of } from 'rxjs'; // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche | rajout de of
import { ExportComponent } from '../export/export.component';
import { SummarySheetComponent } from '../summarySheet/summary-sheet.component';
import { PrintedFolderModalComponent } from '@appRoot/printedFolder/printed-folder-modal.component';
import { catchError, filter, tap } from "rxjs/operators"; // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
import { FunctionsService } from '@service/functions.service'; // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
import { NotificationService } from '@service/notification/notification.service'; // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
import { ConfirmActionComponent } from "@appRoot/actions/confirm-action/confirm-action.component"; // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche

export interface StateGroup {
    letter: string;
    names: any[];
}

@Component({
    selector: 'app-tools-list',
    templateUrl: 'tools-list.component.html',
    styleUrls: ['tools-list.component.scss'],
})
export class ToolsListComponent implements OnInit {

    @ViewChild(MatAutocompleteTrigger, { static: true }) autocomplete: MatAutocompleteTrigger;

    priorities: any[] = [];
    categories: any[] = [];
    entitiesList: any[] = [];
    statuses: any[] = [];
    metaSearchInput: string = '';

    stateGroups: StateGroup[] = [];
    stateGroupOptions: Observable<StateGroup[]>;

    isLoading: boolean = false;

    @Input('listProperties') listProperties: any;
    @Input('currentBasketInfo') currentBasketInfo: any;

    @Input('snavR') sidenavRight: MatSidenav;
    @Input('selectedRes') selectedRes: any;
    @Input('totalRes') totalRes: number;

    constructor(
        public translate: TranslateService,
        public http: HttpClient,
        public dialog: MatDialog,
        private functions: FunctionsService, // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
        private notify: NotificationService, // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
    ) { }

    ngOnInit(): void {

    }

    openExport(): void {
        this.dialog.open(ExportComponent, {
            panelClass: 'maarch-modal',
            width: '800px',
            data: {
                selectedRes: this.selectedRes
            }
        });
    }

    openSummarySheet(): void {
        this.dialog.open(SummarySheetComponent, {
            panelClass: 'maarch-full-height-modal',
            width: '800px',
            data: {
                selectedRes: this.selectedRes
            }
        });
    }

    openPrintedFolderPrompt() {
        this.dialog.open(
            PrintedFolderModalComponent, {
                panelClass: 'maarch-modal',
                width: '800px',
                data: {
                    resId: this.selectedRes,
                    multiple: this.selectedRes.length > 1
                }
            });
    }

    // EDISSYUM - NCH01 Rajout de l'export PESv2 dans la recherche
    runExportPESV2() {
        const dialogRef = this.dialog.open(ConfirmActionComponent, {
            panelClass: 'maarch-modal',
            disableClose: true,
            width: '500px',
            data: {
                'action' : {
                    'label' : this.translate.instant('lang.exportPESV2Resource'),
                },
                'resIds': this.selectedRes,
                'fromSearch': true,
                'resource': {
                    'chrono': '1 ' + this.translate.instant('lang.elements')
                }
            }
        });

        dialogRef.afterClosed().pipe(
            filter((resIds: any) => !this.functions.empty(resIds)),
            tap(() => {
                this.notify.success(this.translate.instant('lang.exportPESV2Resource') + ' en cours de traitement...');
                this.http.post('../rest/pesv2', {'resources' : this.selectedRes, 'fromSearch': true}).pipe(
                    tap(() => {
                        this.notify.success(this.translate.instant('lang.exportPESV2Resource') + ' effectué avec succès');
                    }),
                    catchError((err: any) => {
                        this.notify.handleErrors(err);
                        return of(false);
                    })
                ).subscribe();
            }),
            catchError((err: any) => {
                this.notify.handleErrors(err);
                return of(false);
            })
        ).subscribe();
    }
    // END EDISSYUM - NCH01
}
